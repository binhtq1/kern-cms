<?php

namespace Kern\Page\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Page\Listeners\RenderingSiteMapListener;
use Kern\Theme\Events\RenderingSiteMapEvent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RenderingSiteMapEvent::class => [
            RenderingSiteMapListener::class,
        ],
    ];
}
