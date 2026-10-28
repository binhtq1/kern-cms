<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Base\Supports\DashboardMenu;

class DashboardMenuRetrieving
{
    use Dispatchable;

    public function __construct(
        public DashboardMenu $dashboardMenu
    ) {}
}
