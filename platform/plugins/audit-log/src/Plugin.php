<?php

namespace Kern\AuditLog;

use Illuminate\Support\Facades\Schema;
use Kern\Dashboard\Models\DashboardWidget;
use Kern\PluginManagement\Abstracts\PluginOperationAbstract;
use Kern\Widget\Models\Widget;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::dropIfExists('audit_histories');

        Widget::query()
            ->where('widget_id', 'widget_audit_logs')
            ->each(fn (DashboardWidget $dashboardWidget) => $dashboardWidget->delete());
    }
}
