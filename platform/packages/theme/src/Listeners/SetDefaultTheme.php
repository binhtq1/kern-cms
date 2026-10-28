<?php

namespace Kern\Theme\Listeners;

use Kern\Setting\Facades\Setting;
use Kern\Theme\Facades\Theme;

class SetDefaultTheme
{
    public function handle(): void
    {
        Setting::forceSet('theme', Theme::getThemeName())->set('show_admin_bar', 1)->save();
    }
}
