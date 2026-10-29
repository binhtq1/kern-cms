<?php

namespace Kern\RequestLog\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Console\PruneCommand;
use Kern\Base\Supports\ServiceProvider;
use Kern\RequestLog\Commands\RequestLogClearCommand;
use Kern\RequestLog\Models\RequestLog;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            RequestLogClearCommand::class,
        ]);

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PruneCommand::class, ['--model' => RequestLog::class])->dailyAt('00:30');
        });
    }
}
