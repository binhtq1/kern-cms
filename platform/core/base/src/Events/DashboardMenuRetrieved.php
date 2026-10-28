<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;
use Kern\Base\Supports\DashboardMenu;

class DashboardMenuRetrieved
{
    use Dispatchable;

    public function __construct(
        public DashboardMenu $dashboardMenu,
        public Collection $menuItems
    ) {}
}
