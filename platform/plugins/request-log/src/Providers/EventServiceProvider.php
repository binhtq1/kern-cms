<?php

namespace Kern\RequestLog\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\RequestLog\Events\RequestHandlerEvent;
use Kern\RequestLog\Listeners\RequestHandlerListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RequestHandlerEvent::class => [
            RequestHandlerListener::class,
        ],
    ];
}
