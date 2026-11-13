<?php

use Illuminate\Routing\Events\RouteMatched;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Theme as BaseTheme;

app('events')->listen(RouteMatched::class, function (): void {
    if (! method_exists(BaseTheme::class, 'registerThemeIconFields')) {
        return;
    }

    Theme::registerThemeIconFields([]);
});
