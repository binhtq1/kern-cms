<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Captcha\Http\Controllers\Settings\CaptchaSettingController;
use Kern\Setting\Http\Middleware\PreserveAdminSettingSecrets;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::middleware(HandleAdminInertiaRequests::class.':settings')->group(function (): void {
            Route::middleware(PreserveAdminSettingSecrets::class)->group(function (): void {
                Route::group(['prefix' => 'settings/captcha', 'as' => 'captcha.settings', 'permission' => 'captcha.settings'], function (): void {
                    Route::get('/', [CaptchaSettingController::class, 'edit']);
                    Route::put('/', [CaptchaSettingController::class, 'update'])->name('.update');
                });
            });

        });
    });
});
