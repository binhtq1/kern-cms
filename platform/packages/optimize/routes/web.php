<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Optimize\Http\Controllers\Settings\OptimizeSettingController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::middleware(HandleAdminInertiaRequests::class.':settings')->group(function (): void {
            Route::group(['prefix' => 'settings'], function (): void {
                Route::get('optimize', [OptimizeSettingController::class, 'edit'])->name('optimize.settings');
                Route::put('optimize', [OptimizeSettingController::class, 'update'])->name('optimize.settings.update')->permission('optimize.settings');
            });

        });
    });
});
