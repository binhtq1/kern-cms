<?php

namespace Kern\Base\Listeners;

use Illuminate\Auth\Events\Login;
use Kern\ACL\Models\User;
use Kern\Base\Facades\DashboardMenu;

class ClearDashboardMenuCachesForLoggedUser
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        DashboardMenu::default()->clearCachesForCurrentUser();
    }
}
