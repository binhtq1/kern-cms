<?php

namespace Kern\AuditLog\Providers;

use Kern\AuditLog\Commands\ActivityLogClearCommand;
use Kern\AuditLog\Commands\CleanOldLogsCommand;
use Kern\Base\Supports\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ActivityLogClearCommand::class,
            CleanOldLogsCommand::class,
        ]);
    }
}
