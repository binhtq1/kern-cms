<?php

namespace Kern\Language\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\DeletedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Installer\Events\InstallerFinished;
use Kern\Language\Listeners\ActivatedPluginListener;
use Kern\Language\Listeners\AddHrefLangListener;
use Kern\Language\Listeners\CopyThemeOptions;
use Kern\Language\Listeners\CopyThemeWidgets;
use Kern\Language\Listeners\CreatedContentListener;
use Kern\Language\Listeners\CreateSelectedLanguageWhenInstallationFinished;
use Kern\Language\Listeners\DeletedContentListener;
use Kern\Language\Listeners\ThemeRemoveListener;
use Kern\Language\Listeners\UpdatedContentListener;
use Kern\PluginManagement\Events\ActivatedPluginEvent;
use Kern\Theme\Events\RenderingSingleEvent;
use Kern\Theme\Events\ThemeRemoveEvent;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UpdatedContentEvent::class => [
            UpdatedContentListener::class,
        ],
        CreatedContentEvent::class => [
            CreatedContentListener::class,
            CopyThemeOptions::class,
            CopyThemeWidgets::class,
        ],
        DeletedContentEvent::class => [
            DeletedContentListener::class,
        ],
        ThemeRemoveEvent::class => [
            ThemeRemoveListener::class,
        ],
        ActivatedPluginEvent::class => [
            ActivatedPluginListener::class,
        ],
        RenderingSingleEvent::class => [
            AddHrefLangListener::class,
        ],
        InstallerFinished::class => [
            CreateSelectedLanguageWhenInstallationFinished::class,
        ],
    ];
}
