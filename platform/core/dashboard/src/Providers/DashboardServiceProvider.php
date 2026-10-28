<?php

namespace Kern\Dashboard\Providers;

use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Supports\DashboardMenuItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Dashboard\Models\DashboardWidget;
use Kern\Dashboard\Models\DashboardWidgetSetting;
use Kern\Dashboard\Repositories\Eloquent\DashboardWidgetRepository;
use Kern\Dashboard\Repositories\Eloquent\DashboardWidgetSettingRepository;
use Kern\Dashboard\Repositories\Interfaces\DashboardWidgetInterface;
use Kern\Dashboard\Repositories\Interfaces\DashboardWidgetSettingInterface;

/**
 * @since 02/07/2016 09:50 AM
 */
class DashboardServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->bind(DashboardWidgetInterface::class, function () {
            return new DashboardWidgetRepository(new DashboardWidget);
        });

        $this->app->bind(DashboardWidgetSettingInterface::class, function () {
            return new DashboardWidgetSettingRepository(new DashboardWidgetSetting);
        });
    }

    public function boot(): void
    {
        $this
            ->setNamespace('core/dashboard')
            ->loadHelpers()
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->publishAssets()
            ->loadMigrations();

        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::make()
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-core-dashboard')
                        ->priority(-9999)
                        ->name('core/base::layouts.dashboard')
                        ->icon('ti ti-home')
                        ->route('dashboard.index')
                        ->permissions(false)
                );
        });
    }
}
