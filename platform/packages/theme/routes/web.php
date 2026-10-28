<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Theme\Http\Controllers\ThemeController;
use Kern\Theme\Http\Controllers\WebsiteTrackingSettingController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        if (config('packages.theme.general.display_theme_manager_in_admin_panel', true)) {
            Route::group(['prefix' => 'theme', 'middleware' => HandleAdminInertiaRequests::class.':themes'], function (): void {
                Route::get('all', [ThemeController::class, 'index'])->name('theme.index');
                Route::post('active', [ThemeController::class, 'postActivateTheme'])->name('theme.active')->permission('theme.index')->middleware('preventDemo');
                Route::post('remove', [ThemeController::class, 'postRemoveTheme'])->name('theme.remove')->permission('theme.index')->middleware('preventDemo');
            });
        }
        Route::group(['prefix' => 'theme/options/{id?}', 'middleware' => HandleAdminInertiaRequests::class.':theme-options'], function (): void {
            Route::get('', [ThemeController::class, 'getOptions'])->name('theme.options');
            Route::post('', [ThemeController::class, 'postUpdate'])->name('theme.options.post')->permission('theme.options');
        });
        Route::group(['prefix' => 'theme/custom-css', 'middleware' => HandleAdminInertiaRequests::class.':theme-assets'], function (): void {
            Route::get('', [ThemeController::class, 'getCustomCss'])->name('theme.custom-css');
            Route::post('', [ThemeController::class, 'postCustomCss'])->name('theme.custom-css.post')->permission('theme.custom-css')->middleware('preventDemo');
        });
        Route::group(['prefix' => 'theme/custom-js', 'middleware' => HandleAdminInertiaRequests::class.':theme-assets'], function (): void {
            Route::get('', [ThemeController::class, 'getCustomJs'])->name('theme.custom-js');
            Route::post('', [ThemeController::class, 'postCustomJs'])->name('theme.custom-js.post')->permission('theme.custom-js')->middleware('preventDemo');
        });
        Route::group(['prefix' => 'theme/custom-html', 'middleware' => HandleAdminInertiaRequests::class.':theme-assets'], function (): void {
            Route::get('', [ThemeController::class, 'getCustomHtml'])->name('theme.custom-html');
            Route::post('', [ThemeController::class, 'postCustomHtml'])->name('theme.custom-html.post')->permission('theme.custom-html')->middleware('preventDemo');
        });
        Route::group(['prefix' => 'theme/robots-txt', 'middleware' => HandleAdminInertiaRequests::class.':theme-assets'], function (): void {
            Route::get('', [ThemeController::class, 'getRobotsTxt'])->name('theme.robots-txt');
            Route::post('', [ThemeController::class, 'postRobotsTxt'])->name('theme.robots-txt.post')->permission('theme.robots-txt')->middleware('preventDemo');
        });
        Route::prefix('settings')->name('settings.')->group(function (): void {
            Route::prefix('website-tracking')->middleware(HandleAdminInertiaRequests::class.':settings')->group(function (): void {
                Route::get('/', [WebsiteTrackingSettingController::class, 'edit'])->name('website-tracking');
                Route::put('/', [WebsiteTrackingSettingController::class, 'update'])->name('website-tracking.update')->permission('settings.website-tracking');
            });
        });
    });
});
