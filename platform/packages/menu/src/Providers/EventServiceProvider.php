<?php

namespace Kern\Menu\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Menu\Listeners\DeleteMenuNodeListener;
use Kern\Menu\Listeners\UpdateMenuNodeUrlListener;
use Kern\Slug\Events\UpdatedSlugEvent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UpdatedSlugEvent::class => [
            UpdateMenuNodeUrlListener::class,
        ],
        DeletedContentEvent::class => [
            DeleteMenuNodeListener::class,
        ],
    ];
}
