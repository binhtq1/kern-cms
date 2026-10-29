<?php

namespace Kern\CustomField\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\CustomField\Listeners\CreatedContentListener;
use Kern\CustomField\Listeners\DeletedContentListener;
use Kern\CustomField\Listeners\UpdatedContentListener;

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
