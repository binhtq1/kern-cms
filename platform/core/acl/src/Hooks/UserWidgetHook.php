<?php

namespace Kern\ACL\Hooks;

use Illuminate\Support\Collection;
use Kern\ACL\Models\User;
use Kern\Dashboard\Supports\DashboardWidgetInstance;

class UserWidgetHook
{
    public static function addUserStatsWidget(array $widgets, Collection $widgetSettings): array
    {
        $users = User::query()->count();

        return (new DashboardWidgetInstance)
            ->setType('stats')
            ->setPermission('users.index')
            ->setTitle(trans('core/acl::users.users'))
            ->setKey('widget_total_users')
            ->setIcon('ti ti-users')
            ->setColor('info')
            ->setStatsTotal($users)
            ->setRoute(route('users.index'))
            ->setColumn('col-12 col-md-6 col-lg-3')
            ->init($widgets, $widgetSettings);
    }
}
