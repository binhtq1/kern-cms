<?php

namespace Kern\Table\Providers;

use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Table\ApiResourceDataTable;
use Kern\Table\CollectionDataTable;
use Kern\Table\EloquentDataTable;
use Kern\Table\QueryDataTable;

class TableServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('core/table')
            ->loadHelpers()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadRoutes()
            ->publishAssets();

        $this->app['config']->set([
            'datatables.engines' => [
                'eloquent' => EloquentDataTable::class,
                'query' => QueryDataTable::class,
                'collection' => CollectionDataTable::class,
                'resource' => ApiResourceDataTable::class,
            ],
        ]);
    }
}
