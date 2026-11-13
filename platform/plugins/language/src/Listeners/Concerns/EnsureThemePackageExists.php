<?php

namespace Kern\Language\Listeners\Concerns;

use Kern\Theme\Theme;

trait EnsureThemePackageExists
{
    public function determineIfThemesExists(): bool
    {
        return class_exists(Theme::class);
    }
}
