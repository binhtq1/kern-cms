<?php

namespace Kern\Installer\Providers;

use Carbon\Carbon;
use Illuminate\Routing\Events\RouteMatched;
use Kern\Base\Events\FinishedSeederEvent;
use Kern\Base\Events\UpdatedEvent;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Installer\Http\Middleware\CheckIfInstalledMiddleware;
use Kern\Installer\Http\Middleware\CheckIfInstallingMiddleware;
use Kern\Installer\Http\Middleware\RedirectIfNotInstalledMiddleware;

class InstallerServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('packages/installer')
            ->loadHelpers()
            ->loadAndPublishConfigurations('installer')
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->loadRoutes()
            ->publishAssets();

        if (! app(CheckIfInstalledMiddleware::class)->alreadyInstalled()) {
            if (config('session.driver') === 'database') {
                config(['session.driver' => 'file']);
            }
            if (config('cache.default') === 'database') {
                config(['cache.default' => 'file']);
            }
        }

        $this->app['events']->listen(RouteMatched::class, function (): void {
            if (defined('INSTALLED_SESSION_NAME')) {
                $router = $this->app->make('router');

                $router->middlewareGroup('install', [CheckIfInstalledMiddleware::class]);
                $router->middlewareGroup('installing', [CheckIfInstallingMiddleware::class]);

                $router->pushMiddlewareToGroup('web', RedirectIfNotInstalledMiddleware::class);
            }
        });

        $this->app['events']->listen([UpdatedEvent::class, FinishedSeederEvent::class], function (): void {
            BaseHelper::saveFileData(storage_path(INSTALLED_SESSION_NAME), Carbon::now()->toDateTimeString());
        });
    }
}
