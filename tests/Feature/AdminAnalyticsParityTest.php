<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\Analytics\Abstracts\AnalyticsAbstract;
use Kern\Analytics\Period;
use Kern\PluginManagement\Services\PluginService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminAnalyticsParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
        $this->withoutVite();
        if (! is_plugin_active('analytics')) {
            $this->assertFalse(app(PluginService::class)->activate('analytics')['error']);
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_dashboard_registers_all_four_analytics_widgets_only_when_enabled(bool $enabled): void
    {
        config(['core.base.admin.modules.dashboard' => 'inertia', 'plugins.analytics.general.enabled_dashboard_widgets' => true]);
        setting()->forceSet('analytics_dashboard_widgets', $enabled ? '1' : '0')->save();
        $headers = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
        $response = $this->get(route('dashboard.index'), $headers)->assertOk()->assertJsonPath('component', 'Dashboard/Index');
        $widgets = collect($response->json('props.widgets'))->filter(fn (array $widget): bool => str_starts_with($widget['name'], 'widget_analytics_'))->values();
        if (! $enabled) {
            $this->assertCount(0, $widgets);

            return;
        }
        $this->assertSame(['Site Analytics', 'Most Visited Pages', 'Top Browsers', 'Top Referrers'], $widgets->pluck('title')->all());
        $this->assertTrue($widgets->every(fn (array $widget): bool => $widget['visible']));
        $this->assertSame('today', $widgets[0]['ranges'][0]['key']);
        $this->assertNull($widgets[0]['column']);
        $this->assertSame('col-md-6 col-sm-6', $widgets[1]['column']);
    }

    #[TestWith(['analytics.general'])]
    #[TestWith(['analytics.page'])]
    #[TestWith(['analytics.browser'])]
    #[TestWith(['analytics.referrer'])]
    public function test_unconfigured_analytics_widget_explains_setup_without_fake_metrics(string $route): void
    {
        setting()->forceSet('analytics_service_account_credentials', '')->save();
        $this->getJson(route($route, ['admin_json' => 1]))->assertOk()
            ->assertJsonPath('error', true)
            ->assertJsonPath('data.configuration.title', 'Widget is not set up')
            ->assertJsonPath('data.configuration.url', route('analytics.settings'))
            ->assertJsonMissingPath('data.stats')->assertJsonMissingPath('data.rows')->assertJsonMissingPath('data.chart');
        $this->getJson(route($route))->assertOk()
            ->assertJsonPath('error', true)
            ->assertJsonPath('data.configuration.title', 'Widget is not set up')
            ->assertJsonMissingPath('data.stats')->assertJsonMissingPath('data.rows')->assertJsonMissingPath('data.chart');
    }

    public function test_analytics_setup_action_requires_settings_permission(): void
    {
        setting()->forceSet('analytics_service_account_credentials', '')->save();
        $this->actingAs(UserFactory::new()->create(['permissions' => ['analytics.general' => true]]));
        $this->getJson(route('analytics.general', ['admin_json' => 1]))->assertOk()
            ->assertJsonPath('data.configuration.url', null);
    }

    #[TestWith([0.0043, '0.43%'])]
    #[TestWith([0.425, '42.5%'])]
    #[TestWith([0.0, '0%'])]
    public function test_analytics_keeps_both_series_country_sessions_and_percentage_precision(float $bounceRate, string $percentage): void
    {
        $analytics = $this->mock(AnalyticsAbstract::class);
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === ['totalUsers', 'screenPageViews'] && $dimension === 'date')
            ->andReturn(collect([['date' => '20261001', 'totalUsers' => 12, 'screenPageViews' => 31]]));
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === 'sessions' && $dimension === 'countryIsoCode')
            ->andReturn(collect([['countryIsoCode' => 'VN', 'sessions' => 17]]));
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === ['sessions', 'totalUsers', 'screenPageViews', 'bounceRate'] && $dimension === 'date')
            ->andReturn(collect([['sessions' => 17, 'totalUsers' => 12, 'screenPageViews' => 31, 'bounceRate' => $bounceRate]]));

        $this->getJson(route('analytics.general', ['admin_json' => 1, 'predefined_range' => 'last_7_days']))->assertOk()
            ->assertJsonPath('error', false)->assertJsonPath('data.stats.Bounce rate', $percentage)
            ->assertJsonPath('data.chart.0.visitors', 12)->assertJsonPath('data.chart.0.pageViews', 31)
            ->assertJsonPath('data.countries', [['VN', 17]]);
    }

    #[TestWith([[[10, 0.4], [30, 0.6]], '55%', 40])]
    #[TestWith([[[0, 0.4], [0, 0.6]], '0%', 0])]
    public function test_bounce_rate_is_weighted_by_sessions_across_days(array $days, string $percentage, int $sessions): void
    {
        $analytics = $this->mock(AnalyticsAbstract::class);
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === ['totalUsers', 'screenPageViews'] && $dimension === 'date')
            ->andReturn(collect([]));
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === 'sessions' && $dimension === 'countryIsoCode')
            ->andReturn(collect([]));
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === ['sessions', 'totalUsers', 'screenPageViews', 'bounceRate'] && $dimension === 'date')
            ->andReturn(collect($days)->map(fn (array $day): array => ['sessions' => $day[0], 'totalUsers' => 0, 'screenPageViews' => 0, 'bounceRate' => $day[1]]));

        $this->getJson(route('analytics.general', ['admin_json' => 1, 'predefined_range' => 'last_7_days']))->assertOk()
            ->assertJsonPath('error', false)->assertJsonPath('data.stats.Bounce rate', $percentage)
            ->assertJsonPath('data.stats.Sessions', $sessions);
    }

    public function test_analytics_tables_keep_headers_metrics_and_public_page_links(): void
    {
        $analytics = $this->mock(AnalyticsAbstract::class);
        $analytics->shouldReceive('fetchMostVisitedPages')->once()->andReturn(collect([
            ['fullPageUrl' => 'example.test/article', 'pageTitle' => 'Article', 'screenPageViews' => 1234],
            ['fullPageUrl' => '', 'pageTitle' => 'Missing URL', 'screenPageViews' => 1],
        ]));
        $analytics->shouldReceive('fetchTopBrowsers')->once()->andReturn(collect([['browser' => 'Firefox', 'sessions' => 42]]));
        $analytics->shouldReceive('fetchTopReferrers')->once()->andReturn(collect([['sessionSource' => '(direct)', 'screenPageViews' => 99]]));
        $this->getJson(route('analytics.page', ['admin_json' => 1]))->assertOk()->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.columns.1.key', 'pageTitle')->assertJsonPath('data.columns.2.key', 'pageViews')
            ->assertJsonPath('data.rows.0.url', 'http://example.test/article')->assertJsonPath('data.rows.0.pageViews', 1234)
            ->assertJsonPath('data.link_key', 'pageTitle')->assertJsonPath('data.target', '_blank');
        $this->getJson(route('analytics.browser', ['admin_json' => 1]))->assertOk()
            ->assertJsonPath('data.columns.2.key', 'sessions')->assertJsonPath('data.rows.0.browser', 'Firefox')->assertJsonPath('data.rows.0.sessions', 42);
        $this->getJson(route('analytics.referrer', ['admin_json' => 1]))->assertOk()
            ->assertJsonPath('data.rows.0.url', '(direct)')->assertJsonPath('data.rows.0.pageViews', 99);
    }

    public function test_empty_analytics_tables_expose_empty_state(): void
    {
        $analytics = $this->mock(AnalyticsAbstract::class);
        $analytics->shouldReceive('fetchMostVisitedPages')->once()->andReturn(collect([]));
        $this->getJson(route('analytics.page', ['admin_json' => 1]))->assertOk()
            ->assertJsonCount(0, 'data.rows')->assertJsonPath('data.empty.title', 'No results found');
    }

    public function test_default_today_chart_fills_hours_in_chronological_order(): void
    {
        $analytics = $this->mock(AnalyticsAbstract::class);
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === ['totalUsers', 'screenPageViews'] && $dimension === 'hour')
            ->andReturn(collect([['hour' => '10', 'totalUsers' => 12, 'screenPageViews' => 31], ['hour' => '02', 'totalUsers' => 3, 'screenPageViews' => 7]]));
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === 'sessions')
            ->andReturn(collect([]));
        $analytics->shouldReceive('performQuery')->once()
            ->withArgs(fn (Period $period, array|string $metrics, string $dimension): bool => $metrics === ['sessions', 'totalUsers', 'screenPageViews', 'bounceRate'])
            ->andReturn(collect([]));
        $response = $this->getJson(route('analytics.general', ['admin_json' => 1]))->assertOk()->assertJsonCount(24, 'data.chart')
            ->assertJsonPath('data.chart.10.visitors', 12)->assertJsonPath('data.chart.2.visitors', 3);
        $this->assertSame(array_map(fn (int $hour): string => $hour.'h', range(0, 23)), array_column($response->json('data.chart'), 'axis'));
    }
}
