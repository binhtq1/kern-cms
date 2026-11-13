<?php

namespace Kern\AuditLog\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\AuditLog\Events\AuditHandlerEvent;
use Kern\AuditLog\Listeners\AuditHandlerListener;
use Kern\AuditLog\Listeners\CreatedContentListener;
use Kern\AuditLog\Listeners\DeletedContentListener;
use Kern\AuditLog\Listeners\LoginListener;
use Kern\AuditLog\Listeners\UpdatedContentListener;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AuditHandlerEvent::class => [
            AuditHandlerListener::class,
        ],
        Login::class => [
            LoginListener::class,
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
