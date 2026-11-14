<?php

namespace Kern\Analytics\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Kern\Base\Supports\ServiceProvider;
use Kern\Dashboard\Events\RenderingDashboardWidgets;
use Kern\Dashboard\Supports\DashboardWidgetInstance;
use Kern\PluginManagement\Events\RenderingPluginListingPage;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['events']->listen(RenderingDashboardWidgets::class, function (): void {
            if (
                ! config('plugins.analytics.general.enabled_dashboard_widgets')
                || setting('analytics_dashboard_widgets', '0') != '1'
            ) {
                return;
            }

            add_filter(DASHBOARD_FILTER_ADMIN_LIST, [$this, 'addAnalyticsWidgets'], 18, 2);
        });

        $this->app['events']->listen(RenderingPluginListingPage::class, function (): void {
            add_filter('core_layout_before_content', [$this, 'showMissingLibraryWarning'], 99);
        });
    }

    public function addAnalyticsWidgets(array $widgets, Collection $widgetSettings): array
    {
        $dashboardWidgetInstance = new DashboardWidgetInstance;

        $dashboardWidgetInstance
            ->setPermission('analytics.general')
            ->setKey('widget_analytics_general')
            ->setTitle(trans('plugins/analytics::analytics.widget_analytics_general'))
            ->setIcon('fas fa-chart-line')
            ->setColor('warning')
            ->setRoute(route('analytics.general'))
            ->setHasLoadCallback(true)
            ->setIsEqualHeight(false)
            ->setSettings(['show_predefined_ranges' => true])
            ->init($widgets, $widgetSettings);

        $dashboardWidgetInstance
            ->setPermission('analytics.page')
            ->setKey('widget_analytics_page')
            ->setTitle(trans('plugins/analytics::analytics.widget_analytics_page'))
            ->setIcon('ti ti-news')
            ->setColor('info')
            ->setRoute(route('analytics.page'))
            ->setColumn('col-md-6 col-sm-6')
            ->setSettings(['show_predefined_ranges' => true])
            ->init($widgets, $widgetSettings);

        $dashboardWidgetInstance
            ->setPermission('analytics.browser')
            ->setKey('widget_analytics_browser')
            ->setTitle(trans('plugins/analytics::analytics.widget_analytics_browser'))
            ->setIcon('fab fa-safari')
            ->setColor('purple')
            ->setRoute(route('analytics.browser'))
            ->setColumn('col-md-6 col-sm-6')
            ->setSettings(['show_predefined_ranges' => true])
            ->init($widgets, $widgetSettings);

        return $dashboardWidgetInstance
            ->setPermission('analytics.referrer')
            ->setKey('widget_analytics_referrer')
            ->setTitle(trans('plugins/analytics::analytics.widget_analytics_referrer'))
            ->setIcon('fas fa-user-friends')
            ->setColor('info')
            ->setRoute(route('analytics.referrer'))
            ->setColumn('col-md-6 col-sm-6')
            ->setSettings(['show_predefined_ranges' => true])
            ->init($widgets, $widgetSettings);
    }

    public function showMissingLibraryWarning(?string $html): ?string
    {
        if (! Route::is('plugins.index') || class_exists('Google\ApiCore\Call')) {
            return $html;
        }

        return $html.view('plugins/analytics::missing-library-warning')->render();
    }
}
