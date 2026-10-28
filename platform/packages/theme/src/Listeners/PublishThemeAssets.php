<?php

namespace Kern\Theme\Listeners;

use Illuminate\Support\Facades\File;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Services\ThemeService;

class PublishThemeAssets
{
    public function handle(): void
    {
        File::delete(theme_path(Theme::getThemeName().'/public/css/style.integration.css'));

        $customCSS = Theme::getStyleIntegrationPath();

        if (File::exists($customCSS)) {
            File::copy($customCSS, storage_path('app/style.integration.css.').time());
        }

        app(ThemeService::class)->publishAssets();
    }
}
