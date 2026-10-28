<?php

use Illuminate\Support\Facades\Route;
use Kern\Api\Http\Controllers\ApiController;
use Kern\Api\Http\Controllers\SanctumTokenController;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;

AdminHelper::registerRoutes(function () {
    Route::name('api.')->prefix('settings/api')->group(function () {
        Route::middleware(HandleAdminInertiaRequests::class.':operations')->prefix('sanctum-token')->name('sanctum-token.')->group(function () {
            Route::resource('/', SanctumTokenController::class)
                ->parameters(['' => 'sanctum-token'])
                ->except('edit', 'update', 'show');
        });

        Route::group(['permission' => 'api.settings', 'middleware' => HandleAdminInertiaRequests::class.':settings'], function () {
            Route::get('/', [ApiController::class, 'edit'])->name('settings');
            Route::put('/', [ApiController::class, 'update'])->name('settings.update');
        });
    });
});
