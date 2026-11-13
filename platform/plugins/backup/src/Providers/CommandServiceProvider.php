<?php

namespace Kern\Backup\Providers;

use Kern\Backup\Commands\BackupCleanCommand;
use Kern\Backup\Commands\BackupCreateCommand;
use Kern\Backup\Commands\BackupListCommand;
use Kern\Backup\Commands\BackupRemoveCommand;
use Kern\Backup\Commands\BackupRestoreCommand;
use Kern\Base\Supports\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            BackupCreateCommand::class,
            BackupRestoreCommand::class,
            BackupRemoveCommand::class,
            BackupListCommand::class,
            BackupCleanCommand::class,
        ]);
    }
}
