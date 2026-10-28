<?php

namespace Kern\PluginManagement\Providers;

use Illuminate\Contracts\Database\Events\MigrationEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\SeederPrepared;
use Kern\Base\Listeners\ClearDashboardMenuCaches;
use Kern\PluginManagement\Events\ActivatedPluginEvent;
use Kern\PluginManagement\Listeners\ActivateAllPlugins;
use Kern\PluginManagement\Listeners\ClearPluginCaches;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MigrationEvent::class => [
            ClearPluginCaches::class,
        ],
        SeederPrepared::class => [
            ActivateAllPlugins::class,
        ],
        ActivatedPluginEvent::class => [
            ClearDashboardMenuCaches::class,
        ],
    ];
}
