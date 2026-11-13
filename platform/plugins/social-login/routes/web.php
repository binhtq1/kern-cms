<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Setting\Http\Middleware\PreserveAdminSettingSecrets;
use Kern\SocialLogin\Http\Controllers\FacebookDataDeletionRequestCallbackController;
use Kern\SocialLogin\Http\Controllers\Settings\SocialLoginSettingController;
use Kern\SocialLogin\Http\Controllers\SocialLoginController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::middleware(HandleAdminInertiaRequests::class.':settings')->group(function (): void {
            Route::middleware(PreserveAdminSettingSecrets::class)->group(function (): void {
                Route::group(['prefix' => 'settings'], function (): void {
                    Route::get('social-login', [SocialLoginSettingController::class, 'edit'])->name('social-login.settings');
                    Route::put('social-login', [SocialLoginSettingController::class, 'update'])->name('social-login.settings.update')->permission('social-login.settings');
                });
            });

        });
    });
    Route::group(['middleware' => ['web', 'core']], function (): void {
        Route::get('auth/{provider}', [SocialLoginController::class, 'redirectToProvider'])->name('auth.social');
        Route::get('auth/callback/{provider}', [SocialLoginController::class, 'handleProviderCallback'])->name('auth.social.callback');
    });
    Route::post('facebook-data-deletion-request-callback', [FacebookDataDeletionRequestCallbackController::class, 'store'])->name('facebook-data-deletion-request-callback');
    Route::get('facebook-deletion-status/{id}', [FacebookDataDeletionRequestCallbackController::class, 'show'])->name('facebook-deletion-status');
});
