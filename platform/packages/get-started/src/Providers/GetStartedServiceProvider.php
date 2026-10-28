<?php

namespace Kern\GetStarted\Providers;

use Illuminate\Support\Facades\Auth;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Dashboard\Events\RenderingDashboardWidgets;

class GetStartedServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this->setNamespace('packages/get-started')
            ->loadAndPublishTranslations()
            ->publishAssets()
            ->loadRoutes()
            ->loadAndPublishViews();

        $this->app['events']->listen(RenderingDashboardWidgets::class, function (): void {
            add_action(DASHBOARD_ACTION_REGISTER_SCRIPTS, function (): void {
                if ($this->shouldShowGetStartedPopup()) {
                    add_filter(DASHBOARD_FILTER_ADMIN_NOTIFICATIONS, function ($html) {
                        $notice = view('packages/get-started::setup-wizard-notice')->render();
                        request()->attributes->set('admin_dashboard_supported_notices', [
                            ...request()->attributes->get('admin_dashboard_supported_notices', []), $notice,
                        ]);

                        return $html.$notice;
                    }, 4);
                }
            }, 234);
        });
    }

    protected function shouldShowGetStartedPopup(): bool
    {
        return ! BaseHelper::hasDemoModeEnabled() &&
            is_in_admin(true) &&
            Auth::guard()->check() &&
            setting('is_completed_get_started') != '1';
    }
}
