<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Kern\Analytics\Abstracts\AnalyticsAbstract;
use Kern\Icon\Facades\Icon;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingDashboardPermissionsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->sourceRoot = base_path();
        $this->fixtureRoot = $this->sourceRoot.'/tmp/acceptance-settings-'.bin2hex(random_bytes(8));
        foreach (['platform/plugins', 'platform/themes', 'public/storage', 'storage/framework/views', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs', 'bootstrap/cache', 'lang', 'resources', 'config'] as $directory) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$directory);
        }
        File::copyDirectory(public_path('build'), $this->fixtureRoot.'/public/build');
        File::copy($this->sourceRoot.'/composer.json', $this->fixtureRoot.'/composer.json');
        Icon::setIconPath($this->sourceRoot.'/platform/core/icon/resources/svg');
        $this->app->setBasePath($this->fixtureRoot);
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $this->app->useStoragePath($this->fixtureRoot.'/storage');
        $this->app->useBootstrapPath($this->fixtureRoot.'/bootstrap');
        $this->app->useLangPath($this->fixtureRoot.'/lang');
        config([
            'packages.installer.installer.enabled' => false,
            'filesystems.default' => 'public',
            'core.media.media.disk' => 'public',
            'filesystems.disks.public.root' => $this->fixtureRoot.'/public/storage',
            'filesystems.disks.local.root' => $this->fixtureRoot.'/storage/private',
            'view.compiled' => $this->fixtureRoot.'/storage/framework/views',
            'purifier.cachePath' => $this->fixtureRoot.'/storage/purifier',
            'packages.plugin-management.general.enable_plugin_list_cache' => false,
        ]);
        Storage::forgetDisk('public');
        Storage::forgetDisk('local');
        Http::preventStrayRequests();
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);
        $this->app->setBasePath($this->sourceRoot);
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    private function analytics(): void
    {
        File::copyDirectory($this->sourceRoot.'/platform/plugins/analytics', plugin_path('analytics'));
        $this->assertFalse(app(PluginService::class)->activate('analytics')['error']);
        config(['plugins.analytics.general.enabled_dashboard_widgets' => true]);
        setting()->forceSet('analytics_dashboard_widgets', 1)->save();
    }

    private function statisticsFixtures(): void
    {
        UserFactory::new()->count(2)->create();
        PageFactory::new()->count(2)->create(['status' => 'published']);
        PageFactory::new()->create(['status' => 'draft']);
        foreach (['first', 'second'] as $name) {
            File::ensureDirectoryExists(theme_path($name));
            File::put(theme_path($name.'/theme.json'), json_encode(['id' => 'acceptance/'.$name, 'name' => $name, 'namespace' => 'Theme\\Acceptance'.$name.'\\', 'version' => '1.0', 'author' => 'Acceptance', 'required_plugins' => []]));
            File::ensureDirectoryExists(plugin_path($name));
            File::put(plugin_path($name.'/plugin.json'), json_encode(['id' => 'acceptance/'.$name, 'name' => $name, 'namespace' => 'Kern\\Acceptance'.$name.'\\', 'provider' => 'Kern\\Acceptance'.$name.'\\Providers\\ServiceProvider', 'version' => '1.0', 'author' => 'Acceptance', 'description' => 'Inactive fixture', 'url' => 'https://example.test']));
        }
    }

    private function statistics(): array
    {
        $response = $this->get(route('dashboard.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Dashboard/Index');

        return collect($response->json('props.widgets'))->where('type', 'stats')->keyBy('name')->all();
    }

    public function test_dashboard_statistics_match_actual_users_published_pages_installed_themes_and_plugins(): void
    {
        $this->analytics();
        $this->statisticsFixtures();

        $statistics = $this->statistics();

        $expected = ['users' => [3, 'users.index'], 'pages' => [2, 'pages.index'], 'themes' => [2, 'theme.index'], 'plugins' => [3, 'plugins.index']];
        $this->assertCount(4, $statistics);
        foreach ($expected as $kind => [$count, $route]) {
            $widget = $statistics['widget_total_'.$kind];
            $this->assertSame($count, $widget['value']);
            $this->assertSame(route($route), $widget['url']);
            $this->get($widget['url'], $this->headers())->assertOk();
        }
    }

    #[TestWith(['users', 'users.index', 4])]
    #[TestWith(['pages', 'pages.index', 2])]
    #[TestWith(['themes', 'theme.index', 2])]
    #[TestWith(['plugins', 'plugins.index', 3])]
    #[TestWith([null, null, null])]
    public function test_statistics_expose_only_the_limited_actors_permitted_resource(?string $kind, ?string $permission, ?int $expectedCount): void
    {
        $this->analytics();
        $this->statisticsFixtures();
        $permissions = ['dashboard.index' => true];
        if ($permission) {
            $permissions[$permission] = true;
        }
        $this->actingAs(UserFactory::new()->create(['permissions' => $permissions]));

        $statistics = $this->statistics();

        if ($kind === null) {
            $this->assertSame([], $statistics);
        } else {
            $this->assertSame(['widget_total_'.$kind], array_keys($statistics));
            $this->assertSame($expectedCount, $statistics['widget_total_'.$kind]['value']);
            $this->assertSame(route($permission), $statistics['widget_total_'.$kind]['url']);
            $this->get(route($permission), $this->headers())->assertOk();
        }
        Http::assertNothingSent();
    }

    public static function analyticsPermissionCombinations(): iterable
    {
        for ($mask = 0; $mask < 16; $mask++) {
            yield 'permission mask '.$mask => [$mask];
        }
    }

    #[DataProvider('analyticsPermissionCombinations')]
    public function test_each_analytics_permission_combination_registers_only_allowed_widgets_and_protects_every_feed(int $mask): void
    {
        $this->analytics();
        $feeds = ['analytics.general', 'analytics.page', 'analytics.browser', 'analytics.referrer'];
        $permissions = ['dashboard.index' => true];
        $expectedWidgets = [];
        foreach ($feeds as $index => $feed) {
            if ($mask & (1 << $index)) {
                $permissions[$feed] = true;
                $expectedWidgets[] = str_replace('analytics.', 'widget_analytics_', $feed);
            }
        }
        $this->actingAs(UserFactory::new()->create(['permissions' => $permissions]));
        $service = $this->mock(AnalyticsAbstract::class);
        $service->shouldReceive('performQuery')->times(($mask & 1) ? 3 : 0)->andReturn(collect());
        $service->shouldReceive('fetchMostVisitedPages')->times(($mask & 2) ? 1 : 0)->andReturn(collect());
        $service->shouldReceive('fetchTopBrowsers')->times(($mask & 4) ? 1 : 0)->andReturn(collect());
        $service->shouldReceive('fetchTopReferrers')->times(($mask & 8) ? 1 : 0)->andReturn(collect());

        $response = $this->get(route('dashboard.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Dashboard/Index');

        $actualWidgets = collect($response->json('props.widgets'))->pluck('name')->filter(fn (string $name): bool => str_starts_with($name, 'widget_analytics_'))->sort()->values()->all();
        sort($expectedWidgets);
        $this->assertSame($expectedWidgets, $actualWidgets);
        foreach ($feeds as $index => $feed) {
            $response = $this->getJson(route($feed, ['admin_json' => 1]));
            if ($mask & (1 << $index)) {
                $response->assertOk()->assertJsonPath('error', false);
            } else {
                $response->assertUnauthorized();
            }
        }
        Http::assertNothingSent();
    }
}
