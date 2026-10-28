<?php

namespace Kern\Dashboard\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Kern\Dashboard\Repositories\Interfaces\DashboardWidgetSettingInterface;
use Kern\Support\Repositories\Eloquent\RepositoryAbstract;

class DashboardWidgetSettingRepository extends RepositoryAbstract implements DashboardWidgetSettingInterface
{
    public function getListWidget(): Collection
    {
        $data = $this->model
            ->select([
                'id',
                'order',
                'settings',
                'widget_id',
            ])
            ->with('widget')
            ->orderBy('order')
            ->where('user_id', Auth::guard()->id())
            ->get();

        $this->resetModel();

        return $data;
    }
}
