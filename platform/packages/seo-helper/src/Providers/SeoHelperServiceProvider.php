<?php

namespace Kern\SeoHelper\Providers;

use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\SeoHelper\Contracts\SeoHelperContract;
use Kern\SeoHelper\Contracts\SeoMetaContract;
use Kern\SeoHelper\Contracts\SeoOpenGraphContract;
use Kern\SeoHelper\Contracts\SeoTwitterContract;
use Kern\SeoHelper\SeoHelper;
use Kern\SeoHelper\SeoMeta;
use Kern\SeoHelper\SeoOpenGraph;
use Kern\SeoHelper\SeoTwitter;

/**
 * @since 02/12/2015 14:09 PM
 */
class SeoHelperServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->bind(SeoMetaContract::class, SeoMeta::class);
        $this->app->bind(SeoHelperContract::class, SeoHelper::class);
        $this->app->bind(SeoOpenGraphContract::class, SeoOpenGraph::class);
        $this->app->bind(SeoTwitterContract::class, SeoTwitter::class);
    }

    public function boot(): void
    {
        $this
            ->setNamespace('packages/seo-helper')
            ->loadHelpers()
            ->loadAndPublishConfigurations(['general'])
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->publishAssets();

        $this->app->register(EventServiceProvider::class);
        $this->app->register(HookServiceProvider::class);
    }
}
