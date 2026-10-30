<?php

namespace Kern\Gallery\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Gallery\Listeners\CreatedContentListener;
use Kern\Gallery\Listeners\DeletedContentListener;
use Kern\Gallery\Listeners\RenderingSiteMapListener;
use Kern\Gallery\Listeners\UpdatedContentListener;
use Kern\Theme\Events\RenderingSiteMapEvent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RenderingSiteMapEvent::class => [
            RenderingSiteMapListener::class,
        ],
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
