<?php

namespace Kern\RequestLog;

use Illuminate\Support\Facades\Schema;
use Kern\Dashboard\Models\DashboardWidget;
use Kern\PluginManagement\Abstracts\PluginOperationAbstract;
use Kern\Widget\Models\Widget;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::dropIfExists('request_logs');

        Widget::query()
            ->where('widget_id', 'widget_request_errors')
            ->each(fn (DashboardWidget $dashboardWidget) => $dashboardWidget->delete());
    }
}
