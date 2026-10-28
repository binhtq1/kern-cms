<?php

namespace Kern\ACL\Providers;

use Kern\ACL\Commands\UserCreateCommand;
use Kern\ACL\Commands\UserPasswordCommand;
use Kern\Base\Supports\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            UserCreateCommand::class,
            UserPasswordCommand::class,
        ]);
    }
}
