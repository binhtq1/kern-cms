<?php

namespace Kern\Base\Listeners;

use Kern\Base\Facades\DashboardMenu;

class ClearDashboardMenuCaches
{
    public function handle(): void
    {
        DashboardMenu::clearCaches();
    }
}
