<?php

namespace Kern\Sitemap\Providers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Sitemap\Sitemap;

class SitemapServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    protected bool $defer = true;

    public function register(): void
    {
        $this->app->bind('sitemap', function (Application $app) {
            $config = $app['config']->get('packages.sitemap.config', []);

            return new Sitemap(
                $config,
                $app[Repository::class],
                $app['config'],
                $app['files'],
                $app[ResponseFactory::class],
                $app['view']
            );
        });

        $this->app->alias('sitemap', Sitemap::class);
    }

    public function boot(): void
    {
        $this
            ->setNamespace('packages/sitemap')
            ->loadAndPublishConfigurations(['config'])
            ->loadAndPublishViews()
            ->publishAssets();

        $this->app['events']->listen([
            CreatedContentEvent::class,
            UpdatedContentEvent::class,
            DeletedContentEvent::class,
        ], function (): void {
            $this->app['cache']->forget('cache_site_map_key');
        });
    }

    public function provides(): array
    {
        return ['sitemap', Sitemap::class];
    }
}
