<?php

namespace Kern\Theme;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Kern\Base\Facades\BaseHelper;
use Kern\Theme\Facades\Theme as ThemeFacade;
use Kern\Theme\Services\ThemeService;

class Manager
{
    protected array $themes = [];

    public function __construct(protected ThemeService $themeService)
    {
        $this->registerTheme(self::getAllThemes());
    }

    public function registerTheme(string|array $theme): void
    {
        if (! is_array($theme)) {
            $theme = [$theme];
        }

        $this->themes = array_merge_recursive($this->themes, $theme);
    }

    public function getAllThemes(): array
    {
        $themes = [];
        $themePath = theme_path();
        foreach (BaseHelper::scanFolder($themePath) as $folder) {
            $jsonFile = $themePath.'/'.$folder.'/theme.json';

            $publicJsonFile = public_path('themes/'.ThemeFacade::getPublicThemeName().'/theme.json');

            if (File::exists($publicJsonFile)) {
                $jsonFile = $publicJsonFile;
            }

            if (! File::exists($jsonFile)) {
                continue;
            }

            $theme = BaseHelper::getFileData($jsonFile);

            if (! empty($theme)) {
                $themeConfig = $this->themeService->getThemeConfig($folder);

                $themes[$folder] = $theme;
                $themes[$folder]['inherit'] = Arr::get($themeConfig, 'inherit');
            }
        }

        return $themes;
    }

    public function getThemes(): array
    {
        return $this->themes;
    }
}
