<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Kern\Api\Models\PersonalAccessToken;
use Kern\AuditLog\Models\AuditHistory;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Forms\Fields\HtmlField;
use Kern\Base\Models\AdminNotification;
use Kern\Base\Supports\Fonts;
use Kern\Base\Supports\GoogleFonts;
use Kern\Language\Providers\HookServiceProvider;
use Kern\Media\Facades\RvMedia;
use Kern\PluginManagement\Services\PluginService;
use Kern\RequestLog\Models\RequestLog;
use Kern\Setting\Forms\GeneralSettingForm;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminSettingsOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
        $this->withoutVite();
        foreach (['captcha', 'social-login', 'analytics', 'backup', 'audit-log', 'request-log'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        foreach (['settings', 'system', 'dashboard', 'operations', 'maintenance'] as $module) {
            config(['core.base.admin.modules.'.$module => 'inertia']);
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_admin_appearance_defaults_keep_the_react_palette_and_save_without_validation_errors(): void
    {
        config(['core.base.general.google_fonts_enabled_cache' => false]);
        $response = $this->get(route('settings.admin-appearance'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.admin.appearance.colors', [])->assertJsonPath('props.admin.appearance.font', null)
            ->assertJsonPath('props.admin.appearance.containerWidth', null)->assertJsonPath('props.admin.appearance.themeMode', null);
        $fields = collect($response->json('props.fields'))->keyBy('name');
        $this->assertSame('#214f7d', $fields['admin_primary_color']['value']);
        $this->assertSame('#e9edf2', $fields['admin_secondary_color']['value']);
        $this->assertSame('#20252b', $fields['admin_text_color']['value']);
        $this->assertSame('Inter', $fields['admin_primary_font']['value']);
        $data = [...$fields->pluck('value', 'name')->all(), 'login_screen_backgrounds' => []];

        $this->put(route('settings.admin-appearance.update'), $data, $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('#214f7d', setting('admin_primary_color'));
        $this->get(route('settings.admin-appearance'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.admin.appearance.colors.primary', '#214f7d')
            ->assertJsonPath('props.admin.appearance.font.family', 'Inter');
        $this->put(route('settings.admin-appearance.update'), [...$data, 'login_screen_backgrounds' => [123]], $this->inertiaHeaders())
            ->assertRedirect()->assertSessionHasErrors('login_screen_backgrounds.0');
    }

    public function test_saved_branding_and_layout_options_are_shared_with_inertia(): void
    {
        config(['core.base.general.google_fonts_enabled_cache' => false]);
        setting()->set([
            'admin_logo' => 'https://example.test/admin-logo.png', 'admin_favicon' => 'https://example.test/favicon.png',
            'admin_logo_max_height' => 54, 'admin_primary_color' => '#123456', 'admin_secondary_color' => '#abcdef',
            'admin_heading_color' => '#234567', 'admin_text_color' => '#345678', 'admin_link_color' => '#456789',
            'admin_link_hover_color' => '#567890', 'admin_primary_font' => 'Roboto',
            'admin_appearance_container_width' => 'container-fluid', 'admin_appearance_show_menu_item_icon' => false,
            'admin_appearance_locale_direction' => 'rtl',
        ])->save();

        $this->get(route('settings.admin-appearance'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.admin.appearance.logo', 'https://example.test/admin-logo.png')
            ->assertJsonPath('props.admin.appearance.favicon', 'https://example.test/favicon.png')
            ->assertJsonPath('props.admin.appearance.logoHeight', 54)
            ->assertJsonPath('props.admin.appearance.containerWidth', 'container-fluid')
            ->assertJsonPath('props.admin.appearance.showMenuItemIcon', false)
            ->assertJsonPath('props.admin.appearance.direction', 'rtl')
            ->assertJsonPath('props.admin.appearance.colors.linkHover', '#567890')
            ->assertJsonPath('props.admin.appearance.font.family', 'Roboto');
        auth()->user()->setMeta('locale_direction', 'ltr');
        auth()->user()->setMeta('theme_mode', 'dark');
        $user = auth()->user()->fresh();
        $this->assertSame('ltr', $user->getMeta('locale_direction'));
        $this->actingAs($user);
        $this->get(route('settings.admin-appearance'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.admin.appearance.direction', 'ltr')->assertJsonPath('props.admin.appearance.themeMode', 'dark');
    }

    public function test_persisted_font_respects_the_existing_local_cache_proxy_and_disabled_setting(): void
    {
        setting()->set('admin_primary_font', 'Roboto')->save();
        $loader = $this->createMock(GoogleFonts::class);
        $loader->expects($this->once())->method('load')->willReturn(new Fonts(googleFontsUrl: 'https://fonts.googleapis.com/css2?family=Roboto', localizedUrl: 'https://example.test/storage/fonts/roboto.css'));
        $this->app->instance('core.google-fonts', $loader);
        config(['core.base.general.google_fonts_enabled' => true, 'core.base.general.google_fonts_enabled_cache' => true]);

        $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.admin.appearance.font.url', 'https://example.test/storage/fonts/roboto.css');
        config(['core.base.general.google_fonts_enabled_cache' => false, 'core.base.general.google_fonts_url' => 'https://fonts.bunny.net']);
        $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.admin.appearance.font.url', 'https://fonts.bunny.net/css2?family=Roboto:wght@300;400;500;600;700&display=swap');
        config(['core.base.general.google_fonts_enabled' => false]);
        $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.admin.appearance.font.url', null);
    }

    public function test_unsupported_appearance_choices_keep_react_available_and_normalize_shared_appearance(): void
    {
        config(['core.base.general.google_fonts_enabled_cache' => false]);
        $fields = collect($this->get(route('settings.admin-appearance'), $this->inertiaHeaders())->assertOk()->json('props.fields'))->pluck('value', 'name')->all();
        $this->put(route('settings.admin-appearance.update'), [...$fields, 'login_screen_backgrounds' => [], 'admin_appearance_layout' => 'horizontal'], $this->inertiaHeaders())
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form');
        $fields = [...$fields, 'login_screen_backgrounds' => [], 'admin_appearance_layout' => 'vertical'];
        foreach (['admin_appearance_custom_css' => 'body { color: red; }', 'admin_appearance_custom_header_js' => 'console.log("legacy");', 'admin_appearance_custom_body_js' => 'console.log("legacy");', 'admin_appearance_custom_footer_js' => 'console.log("legacy");'] as $key => $value) {
            $this->put(route('settings.admin-appearance.update'), [...$fields, $key => $value], $this->inertiaHeaders())
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($value, setting($key));
            $response = $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form');
            $this->assertIsArray($response->json('props.admin.appearance'));
        }
        foreach (['admin_primary_color' => 'var(--legacy-color)', 'admin_primary_font' => 'Invalid Custom Font'] as $key => $value) {
            setting()->forceSet($key, $value)->save();
            $this->assertSame($value, setting($key));
            $response = $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form');
            if ($key === 'admin_primary_color') {
                $this->assertArrayNotHasKey('primary', $response->json('props.admin.appearance.colors'));
            } else {
                $this->assertNull($response->json('props.admin.appearance.font'));
            }
        }
        $this->put(route('settings.admin-appearance.update'), [...$fields, 'rich_editor' => 'tinymce'], $this->inertiaHeaders())
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form');
        $this->get(route('dashboard.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Dashboard/Index');
    }

    public function test_settings_extension_metabox_is_blocked_with_compatibility_screen(): void
    {
        GeneralSettingForm::beforeRendering(fn (GeneralSettingForm $form) => $form->addMetaBoxes(['extension-panel' => ['title' => 'Extra settings', 'content' => '<p>Extension settings</p>']]));

        $this->get(route('settings.general'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_settings_forms_are_structured_and_ignore_legacy_module_flag(): void
    {
        foreach (['settings.general', 'settings.admin-appearance', 'settings.cache', 'settings.datatables', 'settings.media', 'settings.email', 'settings.email.rules', 'captcha.settings', 'social-login.settings', 'analytics.settings', 'optimize.settings', 'api.settings', 'settings.website-tracking'] as $route) {
            $response = $this->get(route($route), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form')->assertJsonStructure(['props' => ['fields', 'submit']]);
            foreach (collect($response->json('props.fields'))->where('type', 'radio') as $field) {
                $this->assertNotEmpty($field['choices'], $route.': '.$field['name']);
            }
        }
        config(['core.base.admin.modules.settings' => 'blade']);
        $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form');
    }

    public function test_general_settings_support_known_language_and_log_plugin_fields(): void
    {
        if (! is_plugin_active('language')) {
            $this->assertFalse(app(PluginService::class)->activate('language')['error']);
        }
        foreach ([HookServiceProvider::class, \Kern\AuditLog\Providers\HookServiceProvider::class, \Kern\RequestLog\Providers\HookServiceProvider::class] as $provider) {
            $this->app->register($provider);
        }
        $response = $this->get(route('settings.general'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form');
        $fields = collect($response->json('props.fields'))->keyBy('name');
        $this->assertSame('notice', $fields['language_instruction']['type']);
        $this->assertStringNotContainsString('<', $fields['language_instruction']['description']);
        $this->assertSame('select', $fields['request_log_data_retention_period']['type']);
        $this->assertSame('select', $fields['audit_log_data_retention_period']['type']);
        $this->assertSame('notice', $fields['cronjob_warning']['type']);
    }

    public function test_email_credentials_are_masked_preserved_and_can_be_replaced(): void
    {
        setting()->set('email_password', 'stored-smtp-secret')->save();
        $response = $this->get(route('settings.email'), $this->inertiaHeaders())->assertOk()->assertDontSee('stored-smtp-secret');
        $password = collect($response->json('props.fields'))->firstWhere('name', 'email_password');
        $this->assertSame('', $password['value']);
        $this->assertTrue($password['stored']);
        $data = ['email_driver' => 'smtp', 'email_from_name' => 'Kern', 'email_from_address' => 'admin@example.test', 'email_host' => 'localhost', 'email_port' => 1025];
        $this->put(route('settings.email.update'), $data, $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('stored-smtp-secret', setting('email_password'));
        $this->put(route('settings.email.update'), [...$data, 'email_password' => 'new-smtp-secret'], $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('new-smtp-secret', setting('email_password'));
    }

    public function test_system_maintenance_and_operations_render_typed_screens(): void
    {
        $this->get(route('system.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'System/Index');
        foreach (['system.info' => 'System/Information', 'system.cronjob' => 'System/Cronjob', 'plugins.index' => 'System/Plugins'] as $route => $component) {
            $this->get(route($route), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', $component);
        }
        foreach (['system.cache', 'backups.index', 'audit-log.index', 'request-log.index', 'api.sanctum-token.index', 'notifications.index'] as $route) {
            $this->get(route($route), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'System/Console');
        }
        $this->get(route('core.global-search', ['keyword' => 'Kern']), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'System/Search');
        $this->get(route('dashboard.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Dashboard/Index');
        $this->get(route('get-started.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'System/Setup');
    }

    public function test_log_bulk_deletion_only_removes_selected_records_and_preserves_content_events(): void
    {
        Event::fake([DeletedContentEvent::class]);
        foreach ([RequestLog::class => 'request-log', AuditHistory::class => 'audit-log'] as $model => $route) {
            $records = collect(range(1, 3))->map(fn ($number) => $model::query()->create($model === RequestLog::class
                ? ['url' => 'https://example.test/error-'.$number, 'status_code' => 404]
                : ['action' => 'updated', 'module' => 'page', 'type' => 'info', 'reference_user' => 0, 'reference_id' => $number, 'reference_name' => 'Page '.$number, 'ip_address' => '192.0.2.1', 'user_id' => auth()->id()]));
            $this->get(route($route.'.index'), $this->inertiaHeaders())->assertOk()
                ->assertJsonPath('props.bulkDelete.url', route($route.'.bulk-destroy'));

            $this->deleteJson(route($route.'.bulk-destroy'), ['ids' => $records->take(2)->pluck('id')->all()])->assertOk()->assertJsonPath('error', false);

            foreach ($records->take(2) as $record) {
                $this->assertModelMissing($record);
                Event::assertDispatched(DeletedContentEvent::class, fn ($event) => $event->screen === $model && $event->data->getKey() === $record->getKey());
            }
            $this->assertModelExists($records->last());
        }
        Event::assertDispatchedTimes(DeletedContentEvent::class, 4);
    }

    public function test_log_bulk_deletion_rejects_stale_duplicate_and_empty_selections_without_deleting_valid_records(): void
    {
        foreach ([RequestLog::class => 'request-log', AuditHistory::class => 'audit-log'] as $model => $route) {
            $record = $model::query()->create($model === RequestLog::class ? ['url' => 'https://example.test/error', 'status_code' => 404] : ['action' => 'updated', 'module' => 'page', 'type' => 'info', 'reference_user' => 0, 'reference_id' => 1, 'reference_name' => 'Page', 'user_id' => auth()->id()]);
            foreach ([[], [$record->id, 999999], [$record->id, $record->id]] as $ids) {
                $this->deleteJson(route($route.'.bulk-destroy'), ['ids' => $ids])->assertUnprocessable()->assertJsonValidationErrors($ids === [] ? 'ids' : 'ids.1');
                $this->assertModelExists($record);
            }
        }
    }

    public function test_log_bulk_deletion_requires_permission_and_is_not_offered_to_read_only_users(): void
    {
        $user = UserFactory::new()->create(['permissions' => ['request-log.index' => true, 'audit-log.index' => true]]);
        $this->actingAs($user);
        foreach ([RequestLog::class => 'request-log', AuditHistory::class => 'audit-log'] as $model => $route) {
            $record = $model::query()->create($model === RequestLog::class ? ['url' => 'https://example.test/error', 'status_code' => 404] : ['action' => 'updated', 'module' => 'page', 'type' => 'info', 'reference_user' => 0, 'reference_id' => 1, 'reference_name' => 'Page', 'user_id' => auth()->id()]);
            $this->get(route($route.'.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.bulkDelete', null);
            $this->deleteJson(route($route.'.bulk-destroy'), ['ids' => [$record->id]])->assertUnauthorized();
            $this->assertModelExists($record);
        }
    }

    public function test_log_bulk_deletion_rolls_back_when_a_model_rejects_deletion(): void
    {
        foreach ([RequestLog::class => 'request-log', AuditHistory::class => 'audit-log'] as $model => $route) {
            $first = $model::query()->create($model === RequestLog::class ? ['url' => 'https://example.test/first', 'status_code' => 404] : ['action' => 'updated', 'module' => 'page', 'type' => 'info', 'reference_user' => 0, 'reference_id' => 1, 'reference_name' => 'Page', 'user_id' => auth()->id()]);
            $second = $model::query()->create($model === RequestLog::class ? ['url' => 'https://example.test/second', 'status_code' => 404] : ['action' => 'updated', 'module' => 'page', 'type' => 'info', 'reference_user' => 0, 'reference_id' => 1, 'reference_name' => 'Page', 'user_id' => auth()->id()]);
            $model::deleting(fn ($record) => $record->id !== $second->id);

            $this->deleteJson(route($route.'.bulk-destroy'), ['ids' => [$first->id, $second->id]])->assertUnprocessable()->assertJsonValidationErrors('ids');

            $this->assertModelExists($first);
            $this->assertModelExists($second);
        }
    }

    public function test_audit_search_retains_actor_reference_and_ip_fields_from_the_blade_activity_line(): void
    {
        $actor = UserFactory::new()->create(['first_name' => 'ActorUnique', 'last_name' => 'Reviewer']);
        $record = AuditHistory::query()->create(['action' => 'updated', 'module' => 'page', 'type' => 'info', 'reference_user' => 0, 'reference_id' => 1, 'reference_name' => 'UniqueReference', 'ip_address' => '192.0.2.50', 'user_id' => $actor->id]);
        foreach (['ActorUnique', 'UniqueReference', '192.0.2.50'] as $search) {
            $this->get(route('audit-log.index', ['search' => $search]), $this->inertiaHeaders())->assertOk()
                ->assertJsonCount(1, 'props.rows')->assertJsonPath('props.rows.0.id', $record->id)
                ->assertJsonPath('props.rows.0.values.user', 'ActorUnique Reviewer')
                ->assertJsonPath('props.rows.0.values.ip_address', '192.0.2.50');
        }
    }

    public function test_data_synchronize_screen_only_contains_import_export_sections(): void
    {
        foreach (['blog', 'translation'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        $response = $this->get(route('tools.data-synchronize'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Operations/Index');
        $sections = collect($response->json('props.sections'));
        $this->assertSame(['data-synchronize-export', 'data-synchronize-import'], $sections->pluck('id')->all());
        $this->assertNotContains('Common', $sections->pluck('title')->all());
        foreach (['export.posts', 'import.posts', 'export.theme-translations', 'import.theme-translations', 'export.other-translations', 'import.other-translations'] as $operation) {
            $this->get(route('tools.data-synchronize.'.$operation.'.index'))->assertOk()->assertViewIs('core/base::admin.app');
        }
        $this->get(route('tools.data-synchronize.import.posts.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.columns.0.required', true)->assertJsonPath('props.columns.1.required', false);
        config(['core.base.admin.modules.operations' => 'blade']);
        $this->get(route('tools.data-synchronize.export.other-translations.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Operations/Export');
    }

    public function test_cleanup_lists_record_counts_and_preserves_protected_tables(): void
    {
        config(['core.base.general.enabled_cleanup_database' => false]);
        Storage::fake(config('filesystems.default'));
        Storage::fake(config('core.media.media.disk'));
        $page = PageFactory::new()->create();
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => 1, 'created_at' => 1]);
        $response = $this->get(route('system.cleanup'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'System/Cleanup')->assertJsonPath('props.enabled', false);
        $tables = collect($response->json('props.tables'))->keyBy(fn (array $table): string => str($table['name'])->afterLast('.')->toString());
        $this->assertTrue($tables['pages']['protected']);
        $this->assertTrue($tables['users']['protected']);
        $this->assertFalse($tables['jobs']['protected']);
        $this->assertSame(1, $tables['jobs']['count']);
        $this->postJson(route('system.cleanup.process'), ['tables' => []])->assertUnauthorized();
        $this->assertDatabaseCount('jobs', 1);

        config(['core.base.general.enabled_cleanup_database' => true]);
        $this->postJson(route('system.cleanup.process'), ['tables' => []])->assertOk()->assertJsonPath('error', false);
        $this->assertModelExists($page);
        $this->assertModelExists(auth()->user());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_backup_restore_requires_post_and_permission(): void
    {
        $this->get(route('backups.restore', 'missing'))->assertStatus(405);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['backups.index' => true]]));
        $this->post(route('backups.restore', 'missing'), [], $this->inertiaHeaders())->assertForbidden();
    }

    public function test_email_template_editor_and_template_settings_are_structured(): void
    {
        $this->get(route('settings.email.template'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/Form')->assertJsonStructure(['props' => ['templates']]);
        $this->get(route('settings.email.template.edit', ['core', 'base', 'header']), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Settings/EmailTemplate');
    }

    public function test_notifications_never_expose_or_mutate_another_permission_scope(): void
    {
        $public = AdminNotification::query()->create(['title' => 'Public notice']);
        $restricted = AdminNotification::query()->create(['title' => 'Restricted notice', 'permission' => 'settings.email']);
        $this->actingAs(UserFactory::new()->create(['permissions' => []]));

        $this->get(route('notifications.index'), $this->inertiaHeaders())->assertOk()->assertJsonCount(1, 'props.rows')->assertJsonPath('props.rows.0.id', $public->id)->assertDontSee('Restricted notice');
        $this->delete(route('notifications.destroy', $restricted->id), [], $this->inertiaHeaders())->assertNotFound();
        $this->put(route('notifications.read-all-notification'), [], $this->inertiaHeaders())->assertRedirect();
        $this->assertNull($restricted->fresh()->read_at);
        $this->assertNotNull($public->fresh()->read_at);
        $this->delete(route('notifications.destroy-all-notification'), [], $this->inertiaHeaders())->assertRedirect();
        $this->assertModelExists($restricted);
        $this->assertModelMissing($public);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['settings.*' => true]]));
        $this->get(route('notifications.index'), $this->inertiaHeaders())->assertOk()->assertJsonCount(1, 'props.rows')->assertJsonPath('props.rows.0.id', $restricted->id);
    }

    public function test_setting_extension_html_is_blocked_and_forbidden_settings_remain_forbidden(): void
    {
        GeneralSettingForm::extend(function ($form): void {
            $form->add('custom_extension_content', HtmlField::class, ['html' => '<div>Extension UI</div>']);
        });
        $this->get(route('settings.general'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->actingAs(UserFactory::new()->create(['permissions' => []]));
        $this->put(route('settings.email.update'), ['email_driver' => 'smtp'], $this->inertiaHeaders())->assertForbidden();
    }

    public function test_token_creation_exposes_plain_text_once_and_revoke_deletes_metadata(): void
    {
        $this->post(route('api.sanctum-token.store'), ['name' => 'Integration token'], $this->inertiaHeaders())->assertRedirect(route('api.settings'));
        $token = PersonalAccessToken::query()->firstOrFail();
        $this->assertSame('Integration token', $token->name);
        $first = $this->get(route('api.settings'), $this->inertiaHeaders())->assertOk();
        $this->assertNotEmpty($first->json('props.plainTextToken'));
        $this->get(route('api.settings'), $this->inertiaHeaders())->assertOk()->assertJsonPath('props.plainTextToken', null);
        $this->get(route('api.sanctum-token.index'), $this->inertiaHeaders())->assertOk()->assertDontSee($token->token);
        $this->delete(route('api.sanctum-token.destroy', $token), [], $this->inertiaHeaders())->assertRedirect();
        $this->assertModelMissing($token);
    }

    public function test_import_export_screens_preserve_typed_columns_and_routes(): void
    {
        if (! is_plugin_active('blog')) {
            $this->assertFalse(app(PluginService::class)->activate('blog')['error']);
        }
        $this->get(route('tools.data-synchronize.export.posts.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Operations/Export')->assertJsonStructure(['props' => ['columns', 'submit', 'counters']]);
        $this->get(route('tools.data-synchronize.import.posts.index'), $this->inertiaHeaders())->assertOk()->assertJsonPath('component', 'Operations/Import')->assertJsonStructure(['props' => ['columns', 'upload', 'validate', 'submit', 'chunkSize']]);
    }

    public function test_media_upload_chunk_settings_are_validated_and_persisted(): void
    {
        $data = ['media_driver' => 'public', 'media_upload_path' => 'uploads', 'media_chunk_enabled' => 1, 'media_chunk_size' => 1048576, 'media_max_file_size' => 5242880];
        foreach (array_keys(RvMedia::getSizes()) as $size) {
            $data['media_sizes_'.$size.'_width'] = 200;
            $data['media_sizes_'.$size.'_height'] = 200;
        }
        $this->put(route('settings.media.update'), $data, $this->inertiaHeaders())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('1', setting('media_chunk_enabled'));
        $this->assertSame('1048576', setting('media_chunk_size'));
        $this->assertSame('5242880', setting('media_max_file_size'));
        $this->put(route('settings.media.update'), [...$data, 'media_chunk_size' => -1], $this->inertiaHeaders())->assertSessionHasErrors('media_chunk_size');
        $this->assertSame('1048576', setting('media_chunk_size'));
    }
}
