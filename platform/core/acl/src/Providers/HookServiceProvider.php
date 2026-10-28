<?php

namespace Kern\ACL\Providers;

use Kern\ACL\Hooks\UserWidgetHook;
use Kern\Base\Supports\ServiceProvider;
use Kern\Dashboard\Events\RenderingDashboardWidgets;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['events']->listen(RenderingDashboardWidgets::class, function (): void {
            add_filter(DASHBOARD_FILTER_ADMIN_LIST, [UserWidgetHook::class, 'addUserStatsWidget'], 12, 2);
        });
    }
}
