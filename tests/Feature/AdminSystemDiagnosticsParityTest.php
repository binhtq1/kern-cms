<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Kern\Setting\Facades\Setting;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminSystemDiagnosticsParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.system' => 'inertia', 'core.base.admin.modules.maintenance' => 'inertia']);
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    #[TestWith([null, 'unconfigured'])]
    #[TestWith([0, 'healthy'])]
    #[TestWith([10, 'healthy'])]
    #[TestWith([11, 'stale'])]
    public function test_cronjob_exposes_blade_health_threshold_and_setup(?int $minutes, string $status): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
        $this->actingAs(UserFactory::new()->superUser()->create());
        if ($minutes !== null) {
            Setting::set('cronjob_last_run_at', now()->subMinutes($minutes)->toDateTimeString())->save();
        }
        $response = $this->get(route('system.cronjob'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('component', 'System/Cronjob')->assertJsonPath('props.status', $status)
            ->assertJsonCount(4, 'props.steps');
        $this->assertStringContainsString('artisan schedule:run', $response->json('props.command'));
        if ($minutes === null) {
            $response->assertJsonPath('props.lastRunAt', null);
        } else {
            $this->assertNotNull($response->json('props.lastRunAt'));
        }
    }

    public function test_system_report_data_is_explicit_and_contains_no_application_secrets(): void
    {
        Http::fake(['https://ipecho.net/plain' => Http::response('203.0.113.25')]);
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32)), 'database.connections.sqlite.password' => 'private-database-secret']);
        $response = $this->get(route('system.info'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('component', 'System/Information')
            ->assertJsonPath('props.urls.additionalData', route('system.info.get-addition-data'));
        $rows = collect($response->json('props.application'))->keyBy('key');
        $this->assertSame(get_cms_version(), $rows['cms_version']['value']);
        $this->assertNull($rows['app_size']['value']);
        $this->assertSame(['cms_version', 'framework_version', 'timezone', 'server_ip', 'debug_mode_off', 'storage_dir_writable', 'cache_dir_writable', 'app_size'], $rows->keys()->all());
        $this->assertStringNotContainsString('private-database-secret', $response->getContent());
        $this->assertStringNotContainsString(config('app.key'), $response->getContent());
    }

    public function test_system_information_and_size_endpoint_keep_superuser_authorization(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $this->get(route('system.info'), $this->inertiaHeaders())->assertForbidden();
        $this->getJson(route('system.info.get-addition-data'))->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_plugins_expose_author_status_and_permission_scoped_actions(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $response = $this->get(route('plugins.index'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('component', 'System/Plugins')->assertJsonPath('props.permissions.edit', true)
            ->assertJsonPath('props.permissions.remove', true);
        $this->assertNotEmpty($response->json('props.plugins'));
        $plugin = $response->json('props.plugins.0');
        $this->assertIsBool($plugin['active']);
        $this->assertArrayHasKey('author', $plugin);
        config(['packages.plugin-management.general.hide_plugin_author' => true]);
        $hidden = $this->get(route('plugins.index'), $this->inertiaHeaders())->assertOk();
        foreach ($hidden->json('props.plugins') as $item) {
            $this->assertNull($item['author']);
        }
    }

    public function test_system_and_plugins_ignore_legacy_module_flags(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['core.base.admin.modules.system' => 'blade', 'core.base.admin.modules.maintenance' => 'blade']);
        foreach (['system.cronjob', 'system.info', 'plugins.index'] as $route) {
            $this->get(route($route), $this->inertiaHeaders())->assertOk();
        }
    }

    #[TestWith([' 203.0.113.26\n', '203.0.113.26'])]
    #[TestWith(['2001:db8::25', '2001:db8::25'])]
    #[TestWith(['invalid remote address', '198.51.100.25'])]
    #[TestWith([null, '198.51.100.25'])]
    public function test_server_ip_uses_valid_remote_response_or_request_fallback_without_exposing_secrets(?string $body, string $expected): void
    {
        Http::fake(['https://ipecho.net/plain' => $body === null ? Http::failedConnection() : Http::response(str_replace('\\n', "\n", $body))]);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])->get(route('system.info'), $this->inertiaHeaders())->assertOk();
        $rows = collect($response->json('props.application'))->keyBy('key');
        $this->assertSame($expected, $rows['server_ip']['value']);
        $this->assertSame(trans('core/base::system.server_ip'), $rows['server_ip']['label']);
        $this->assertNull($rows['app_size']['value']);
        Http::assertSentCount(1);
    }
}
