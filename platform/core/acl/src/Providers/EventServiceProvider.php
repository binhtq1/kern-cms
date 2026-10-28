<?php

namespace Kern\ACL\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\ACL\Events\RoleAssignmentEvent;
use Kern\ACL\Events\RoleUpdateEvent;
use Kern\ACL\Listeners\LoginListener;
use Kern\ACL\Listeners\RoleAssignmentListener;
use Kern\ACL\Listeners\RoleUpdateListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        RoleUpdateEvent::class => [
            RoleUpdateListener::class,
        ],
        RoleAssignmentEvent::class => [
            RoleAssignmentListener::class,
        ],
        Login::class => [
            LoginListener::class,
        ],
    ];
}
