<?php

namespace Kern\LanguageAdvanced\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Kern\Base\Events\CreatedContentEvent;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\LanguageAdvanced\Listeners\AddDefaultTranslations;
use Kern\LanguageAdvanced\Listeners\AddRefLangToAdminBar;
use Kern\LanguageAdvanced\Listeners\ClearCacheAfterUpdateData;
use Kern\LanguageAdvanced\Listeners\PriorityLanguageAdvancedPluginListener;
use Kern\LanguageAdvanced\Listeners\UpdatePermalinkSettingsForEachLanguage;
use Kern\PluginManagement\Events\ActivatedPluginEvent;
use Kern\Slug\Events\UpdatedPermalinkSettings;
use Kern\Theme\Events\RenderingAdminBar;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        CreatedContentEvent::class => [
            AddDefaultTranslations::class,
        ],
        UpdatedContentEvent::class => [
            ClearCacheAfterUpdateData::class,
        ],
        ActivatedPluginEvent::class => [
            PriorityLanguageAdvancedPluginListener::class,
        ],
        UpdatedPermalinkSettings::class => [
            UpdatePermalinkSettingsForEachLanguage::class,
        ],
        RenderingAdminBar::class => [
            AddRefLangToAdminBar::class,
        ],
    ];
}
