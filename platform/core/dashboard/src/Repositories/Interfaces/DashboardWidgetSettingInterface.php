<?php

namespace Kern\Dashboard\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Kern\Support\Repositories\Interfaces\RepositoryContract;

interface DashboardWidgetSettingInterface extends RepositoryContract
{
    public function getListWidget(): Collection;
}
