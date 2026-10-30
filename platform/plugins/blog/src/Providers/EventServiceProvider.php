<?php

namespace Kern\Blog\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Blog\Listeners\RenderingSiteMapListener;
use Kern\Theme\Events\RenderingSiteMapEvent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RenderingSiteMapEvent::class => [
            RenderingSiteMapListener::class,
        ],
    ];
}
