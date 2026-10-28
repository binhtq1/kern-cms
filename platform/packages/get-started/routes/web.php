<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\GetStarted\Http\Controllers\GetStartedController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'get-started', 'middleware' => HandleAdminInertiaRequests::class.':operations'], function (): void {
            Route::get('/', [GetStartedController::class, 'index'])->name('get-started.index')->permission(false);
            Route::post('save', [GetStartedController::class, 'save'])->name('get-started.save')->permission(false)->middleware('preventDemo');
        });
    });
});
