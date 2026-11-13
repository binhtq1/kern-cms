<?php

use Kern\Shortcode\View\View;
use Kern\Theme\Theme;

return [
    'inherit' => null,
    'events' => [
        'beforeRenderTheme' => function (Theme $theme): void {
            if (function_exists('shortcode')) {
                $theme->composer(['page', 'post'], function (View $view): void {
                    $view->withShortcodes();
                });
            }
        },
    ],
];
