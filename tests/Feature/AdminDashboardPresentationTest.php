<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Kern\Dashboard\Models\DashboardWidget;
use Kern\Dashboard\Models\DashboardWidgetSetting;
use Kern\Dashboard\Supports\DashboardWidgetInstance;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminDashboardPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.dashboard' => 'inertia']);
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    private function dashboard(): TestResponse
    {
        return $this->get(route('dashboard.index'), ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''])->assertOk()->assertJsonPath('component', 'Dashboard/Index');
    }

    public function test_updater_urls_are_exposed_only_when_enabled_for_a_superuser(): void
    {
        config(['core.base.general.enable_system_updater' => true]);
        $this->dashboard()->assertJsonPath('props.updater', null);
        Route::get('/presentation/check-update', fn () => [])->name('system.check-update');
        Route::get('/presentation/updater', fn () => [])->name('system.updater');
        Route::getRoutes()->refreshNameLookups();
        $this->dashboard()->assertJsonPath('props.updater.check', route('system.check-update'))->assertJsonPath('props.updater.url', route('system.updater'));
        config(['core.base.general.enable_system_updater' => false]);
        $this->dashboard()->assertJsonPath('props.updater', null);
        config(['core.base.general.enable_system_updater' => true]);
        $this->actingAs(UserFactory::new()->create(['permissions' => ['dashboard.index' => true]]));
        $this->dashboard()->assertJsonPath('props.updater', null);
    }

    public function test_widget_ranges_require_explicit_support_and_stat_metadata_is_preserved(): void
    {
        add_filter(DASHBOARD_FILTER_ADMIN_LIST, function (array $widgets, $settings): array {
            foreach ([false, true] as $showRanges) {
                (new DashboardWidgetInstance)->setKey($showRanges ? 'presentation_dates' : 'presentation_plain')->setTitle('Presentation')->setPermission('dashboard.index')->setRoute(route('dashboard.index'))->setColumn('col-md-6')->setSettings(['show_predefined_ranges' => $showRanges])->init($widgets, $settings);
            }
            (new DashboardWidgetInstance)->setKey('presentation_stats')->setTitle('Statistics')->setPermission('dashboard.index')->setRoute(route('dashboard.index'))->setType('stats')->setIcon('ti ti-users')->setColor('info')->setStatsTotal(12500)->setColumn('col-12 col-md-6 col-lg-3')->init($widgets, $settings);

            return $widgets;
        }, 2000, 2);
        $widgets = collect($this->dashboard()->json('props.widgets'))->keyBy('name');
        $this->assertSame([], $widgets['presentation_plain']['ranges']);
        $this->assertSame('today', $widgets['presentation_dates']['ranges'][0]['key']);
        $this->assertCount(7, $widgets['presentation_dates']['ranges']);
        $this->assertSame('col-md-6', $widgets['presentation_dates']['column']);
        $this->assertSame('ti ti-users', $widgets['presentation_stats']['icon']);
        $this->assertSame('info', $widgets['presentation_stats']['color']);
        $this->assertSame(12500, $widgets['presentation_stats']['value']);
    }

    public function test_saved_widget_order_and_visibility_survive_reload_without_affecting_other_users(): void
    {
        add_filter(DASHBOARD_FILTER_ADMIN_LIST, function (array $widgets, $settings): array {
            foreach (['presentation_first', 'presentation_second'] as $name) {
                (new DashboardWidgetInstance)->setKey($name)->setTitle($name)->setPermission('dashboard.index')->setRoute(route('dashboard.index'))->init($widgets, $settings);
            }

            return $widgets;
        }, 2000, 2);
        $this->dashboard();
        $this->postJson(route('dashboard.update_widget_order'), ['items' => ['presentation_second', 'presentation_first']])->assertOk()->assertJsonPath('error', false);
        $widgets = collect($this->dashboard()->json('props.widgets'))->whereIn('name', ['presentation_first', 'presentation_second'])->values();
        $this->assertSame(['presentation_second', 'presentation_first'], $widgets->pluck('name')->all());
        $first = DashboardWidget::query()->where('name', 'presentation_first')->firstOrFail();
        DashboardWidgetSetting::query()->where('widget_id', $first->id)->where('user_id', auth()->id())->update(['status' => false]);
        $this->assertFalse(collect($this->dashboard()->json('props.widgets'))->firstWhere('name', 'presentation_first')['visible']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->assertTrue(collect($this->dashboard()->json('props.widgets'))->firstWhere('name', 'presentation_first')['visible']);
    }
}
