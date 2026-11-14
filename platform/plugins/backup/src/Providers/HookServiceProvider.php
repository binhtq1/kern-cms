<?php

namespace Kern\Backup\Providers;

use Kern\Base\Facades\BaseHelper;
use Kern\Base\Supports\ServiceProvider;
use Kern\Dashboard\Events\RenderingDashboardWidgets;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['events']->listen(RenderingDashboardWidgets::class, function (): void {
            add_filter(DASHBOARD_FILTER_ADMIN_NOTIFICATIONS, [$this, 'registerAdminAlert'], 5);
        });
    }

    public function registerAdminAlert(?string $alert): string
    {
        if (! BaseHelper::hasDemoModeEnabled()) {
            return $alert ?? '';
        }

        $notice = view('plugins/backup::partials.admin-alert')->render();
        request()->attributes->set('admin_dashboard_supported_notices', [
            ...request()->attributes->get('admin_dashboard_supported_notices', []), $notice,
        ]);

        return $alert.$notice;
    }
}
