<?php

use Illuminate\Support\Facades\Route;
use Kern\Slug\Facades\SlugHelper;
use Kern\Theme\Events\ThemeRoutingAfterEvent;
use Kern\Theme\Events\ThemeRoutingBeforeEvent;
use Kern\Theme\Facades\SiteMapManager;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Http\Controllers\PublicController;

Theme::registerRoutes(function (): void {
    Route::group(['controller' => PublicController::class], function (): void {
        event(new ThemeRoutingBeforeEvent(app()->make('router')));

        Route::get('/', 'getIndex')->name('public.index');

        Route::get('{key}.{extension}', 'getSiteMapIndex')
            ->where('key', '^'.collect(SiteMapManager::getKeys())->map(fn ($item) => '(?:'.$item.')')->implode('|').'$')
            ->whereIn('extension', SiteMapManager::allowedExtensions())
            ->name('public.sitemap.index');

        Route::get('{slug?}', 'getView')->name('public.single');

        Route::get('{prefix}/{slug?}', 'getViewWithPrefix')
            ->whereIn('prefix', SlugHelper::getAllPrefixes() ?: ['1437bcd2-d94e-4a5fd-9a39-b5d60225e9af']);

        event(new ThemeRoutingAfterEvent(app()->make('router')));
    });
});
