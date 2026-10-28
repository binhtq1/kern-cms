<?php

namespace Kern\Base\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\AboutCommand;
use Kern\Base\Commands\CleanupSystemCommand;
use Kern\Base\Commands\ClearExpiredCacheCommand;
use Kern\Base\Commands\ClearLogCommand;
use Kern\Base\Commands\ExportDatabaseCommand;
use Kern\Base\Commands\FetchGoogleFontsCommand;
use Kern\Base\Commands\ImportDatabaseCommand;
use Kern\Base\Commands\InstallCommand;
use Kern\Base\Commands\PublishAssetsCommand;
use Kern\Base\Commands\UpgradeCommand;
use Kern\Base\Supports\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CleanupSystemCommand::class,
            ClearExpiredCacheCommand::class,
            ClearLogCommand::class,
            ExportDatabaseCommand::class,
            FetchGoogleFontsCommand::class,
            ImportDatabaseCommand::class,
            InstallCommand::class,
            PublishAssetsCommand::class,
            UpgradeCommand::class,
        ]);

        AboutCommand::add('Core Information', fn () => [
            'CMS Version' => get_cms_version(),
            'Core Version' => get_core_version(),
        ]);

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ClearExpiredCacheCommand::class)->everyFiveMinutes();
        });
    }
}
