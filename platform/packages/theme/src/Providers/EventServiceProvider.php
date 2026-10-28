<?php

namespace Kern\Theme\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\FormRendering;
use Kern\Base\Events\SeederPrepared;
use Kern\Theme\Listeners\AddFormJsValidation;
use Kern\Theme\Listeners\SetDefaultTheme;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SeederPrepared::class => [
            SetDefaultTheme::class,
        ],
        FormRendering::class => [
            AddFormJsValidation::class,
        ],
    ];
}
