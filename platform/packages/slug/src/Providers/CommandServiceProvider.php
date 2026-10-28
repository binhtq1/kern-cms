<?php

namespace Kern\Slug\Providers;

use Kern\Base\Supports\ServiceProvider;
use Kern\Slug\Commands\ChangeSlugPrefixCommand;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ChangeSlugPrefixCommand::class,
        ]);
    }
}
