<?php

namespace Kern\Icon\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Blade;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Icon\Commands\IconUpdateCommand;
use Kern\Icon\Facades\Icon as IconFacade;
use Kern\Icon\IconManager;
use Kern\Icon\View\Components\Icon;

class IconServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this
            ->setNamespace('core/icon')
            ->loadAndPublishConfigurations('icon');

        $this->app->singleton(IconManager::class);
    }

    public function boot(): void
    {
        Blade::component('core::icon', Icon::class);

        $aliasLoader = AliasLoader::getInstance();

        if (! class_exists('CoreIcon')) {
            $aliasLoader->alias('CoreIcon', IconFacade::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([IconUpdateCommand::class]);
        }
    }
}
