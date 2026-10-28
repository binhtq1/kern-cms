<?php

namespace Kern\Member\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Member\Listeners\UpdatedContentListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UpdatedContentEvent::class => [
            UpdatedContentListener::class,
        ],
    ];
}
