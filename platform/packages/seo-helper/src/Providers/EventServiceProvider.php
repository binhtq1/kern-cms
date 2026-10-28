<?php

namespace Kern\SeoHelper\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\SeoHelper\Listeners\CreatedContentListener;
use Kern\SeoHelper\Listeners\DeletedContentListener;
use Kern\SeoHelper\Listeners\UpdatedContentListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UpdatedContentEvent::class => [
            UpdatedContentListener::class,
        ],
        CreatedContentEvent::class => [
            CreatedContentListener::class,
        ],
        DeletedContentEvent::class => [
            DeletedContentListener::class,
        ],
    ];
}
