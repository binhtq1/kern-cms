<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\Base\Models\AdminNotification;
use Kern\PluginManagement\Services\PluginService;
use Kern\Theme\Facades\ThemeOption;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminDashboardNoticesParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        foreach (['dashboard', 'system', 'operations'] as $module) {
            config(['core.base.admin.modules.'.$module => 'inertia']);
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_supported_dashboard_notice_is_cleaned_and_setup_link_is_actionable(): void
    {
        setting()->forceSet('is_completed_get_started', '0')->save();
        $response = $this->get(route('dashboard.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Dashboard/Index');
        $html = implode('', array_column($response->json('props.notices'), 'html'));
        $this->assertStringContainsString(route('get-started.index'), $html);
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_unknown_dashboard_notification_extension_is_blocked_instead_of_disappearing(): void
    {
        add_filter(DASHBOARD_FILTER_ADMIN_NOTIFICATIONS, fn (?string $html): string => $html.'<p>Extension alert</p>', 1000);
        $this->get(route('dashboard.index'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_unknown_top_block_is_blocked_with_compatibility_screen(): void
    {
        add_filter(DASHBOARD_FILTER_TOP_BLOCKS, fn (?string $html): string => $html.'<div>Extension top block</div>', 1000);
        $this->get(route('dashboard.index'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_supported_notice_schema_strips_scripts_handlers_and_unsafe_links(): void
    {
        add_filter(DASHBOARD_FILTER_ADMIN_NOTIFICATIONS, function (?string $html): string {
            $notice = '<p onclick="alert(1)">Safe notice<script>alert(1)</script><a href="javascript:alert(1)">Unsafe link</a></p>';
            request()->attributes->set('admin_dashboard_supported_notices', [...request()->attributes->get('admin_dashboard_supported_notices', []), $notice]);

            return $html.$notice;
        }, 1000);
        $response = $this->get(route('dashboard.index'), $this->inertiaHeaders())->assertOk();
        $html = implode('', array_column($response->json('props.notices'), 'html'));
        $this->assertStringContainsString('Safe notice', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_notifications_expose_unread_state_and_clear_only_visible_permission_scope(): void
    {
        $unread = AdminNotification::query()->create(['title' => 'Unread public notice']);
        AdminNotification::query()->create(['title' => 'Read public notice', 'read_at' => now()]);
        $restricted = AdminNotification::query()->create(['title' => 'Private notice', 'permission' => 'settings.email']);
        $this->actingAs(UserFactory::new()->create(['permissions' => []]));
        $response = $this->get(route('notifications.index'), $this->inertiaHeaders())->assertOk()->assertJsonCount(2, 'props.rows')->assertDontSee('Private notice');
        $rows = collect($response->json('props.rows'))->keyBy('id');
        $this->assertTrue($rows[$unread->id]['unread']);
        $this->assertSame(__('Unread'), $rows[$unread->id]['values']['status']);
        $clear = collect($response->json('props.actions'))->firstWhere('method', 'delete');
        $this->assertSame(route('notifications.destroy-all-notification'), $clear['url']);
        $this->assertNotEmpty($clear['confirm']);
        $this->delete($clear['url'], [], $this->inertiaHeaders())->assertRedirect();
        $this->assertModelMissing($unread);
        $this->assertModelExists($restricted);
    }

    public function test_wizard_exposes_supported_theme_fields_and_saves_admin_branding(): void
    {
        ThemeOption::setField(['id' => 'primary_color', 'section_id' => 'general', 'type' => 'customColor', 'label' => 'Color']);
        ThemeOption::setField(['id' => 'primary_font', 'section_id' => 'general', 'type' => 'googleFonts', 'label' => 'Font']);
        $this->get(route('get-started.index'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.themeFields.primary_color', true)->assertJsonPath('props.themeFields.primary_font', true)
            ->assertJsonStructure(['props' => ['site' => ['admin_logo', 'admin_favicon', 'primary_color', 'primary_font'], 'fonts']]);
        $this->postJson(route('get-started.save'), ['step' => 2, 'site_title' => 'Wizard site', 'admin_logo' => 'branding/admin-logo.png', 'admin_favicon' => 'branding/favicon.png', 'primary_color' => '#245c4c', 'primary_font' => 'Inter'])->assertOk()->assertJsonPath('error', false);
        $this->assertSame('branding/admin-logo.png', setting('admin_logo'));
        $this->assertSame('branding/favicon.png', setting('admin_favicon'));
        $this->assertSame('#245c4c', theme_option('primary_color'));
    }

    public function test_block_form_keeps_code_mode_placeholder_and_raw_content_precedence_help(): void
    {
        if (! is_plugin_active('block')) {
            $this->assertFalse(app(PluginService::class)->activate('block')['error']);
        }
        config(['core.base.admin.modules.blocks' => 'inertia']);
        $response = $this->get(route('block.create'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Blocks/Form');
        $fields = collect($response->json('props.fields'))->keyBy('name');
        $this->assertSame('code', $fields['raw_content']['type']);
        $this->assertSame('html', $fields['raw_content']['mode']);
        $this->assertSame(trans('plugins/block::block.raw_content_placeholder'), $fields['raw_content']['placeholder']);
        $this->assertSame(trans('plugins/block::block.content_helper_text'), $fields['content']['description']);
        $this->assertSame(trans('plugins/block::block.raw_content_helper_text'), $fields['raw_content']['description']);
    }
}
