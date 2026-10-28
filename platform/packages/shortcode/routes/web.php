<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\RequiresJsonRequestMiddleware;
use Kern\Shortcode\Http\Controllers\ShortcodeController;
use Kern\Theme\Facades\Theme;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'short-codes'], function (): void {
            Route::post('ajax-get-admin-config/{key}', [ShortcodeController::class, 'ajaxGetAdminConfig'])->name('short-codes.ajax-get-admin-config')->permission(false);
        });
    });
});
app()->booted(function (): void {
    Route::middleware(RequiresJsonRequestMiddleware::class)->group(function (): void {
        Theme::registerRoutes(function (): void {
            Route::post('ajax/render-ui-blocks', [ShortcodeController::class, 'ajaxRenderUiBlock'])->name('public.ajax.render-ui-block');
        });
    });
});
