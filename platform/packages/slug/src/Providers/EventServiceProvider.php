<?php

namespace Kern\Slug\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\FinishedSeederEvent;
use Kern\Base\Events\SeederPrepared;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Slug\Listeners\CreatedContentListener;
use Kern\Slug\Listeners\CreateMissingSlug;
use Kern\Slug\Listeners\DeletedContentListener;
use Kern\Slug\Listeners\TruncateSlug;
use Kern\Slug\Listeners\UpdatedContentListener;

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
        SeederPrepared::class => [
            TruncateSlug::class,
        ],
        FinishedSeederEvent::class => [
            CreateMissingSlug::class,
        ],
    ];
}
