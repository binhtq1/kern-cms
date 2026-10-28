<?php

namespace Kern\PluginManagement\Providers;

use Kern\Base\Supports\ServiceProvider;
use Kern\PluginManagement\Commands\ClearCompiledCommand;
use Kern\PluginManagement\Commands\PluginActivateAllCommand;
use Kern\PluginManagement\Commands\PluginActivateCommand;
use Kern\PluginManagement\Commands\PluginAssetsPublishCommand;
use Kern\PluginManagement\Commands\PluginDeactivateAllCommand;
use Kern\PluginManagement\Commands\PluginDeactivateCommand;
use Kern\PluginManagement\Commands\PluginDiscoverCommand;
use Kern\PluginManagement\Commands\PluginListCommand;
use Kern\PluginManagement\Commands\PluginRemoveAllCommand;
use Kern\PluginManagement\Commands\PluginRemoveCommand;
use Kern\PluginManagement\Commands\PluginUpdateVersionInfoCommand;

class CommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PluginAssetsPublishCommand::class,
                ClearCompiledCommand::class,
                PluginDiscoverCommand::class,
                PluginActivateCommand::class,
                PluginActivateAllCommand::class,
                PluginDeactivateCommand::class,
                PluginDeactivateAllCommand::class,
                PluginRemoveCommand::class,
                PluginRemoveAllCommand::class,
                PluginListCommand::class,
                PluginUpdateVersionInfoCommand::class,
            ]);
        }
    }
}
