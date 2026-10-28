<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\DisableInDemoModeMiddleware;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\DataSynchronize\Http\Controllers\DataSynchronizeController;
use Kern\DataSynchronize\Http\Controllers\UploadController;

AdminHelper::registerRoutes(function () {
    Route::middleware(HandleAdminInertiaRequests::class.':operations')->group(function (): void {
        Route::group(['permission' => 'tools.data-synchronize'], function () {
            Route::get('tools/data-synchronize', [DataSynchronizeController::class, 'index'])
                ->name('tools.data-synchronize');

            Route::prefix('data-synchronize')->name('data-synchronize.')->group(function () {
                Route::post('upload', [UploadController::class, '__invoke'])
                    ->middleware(DisableInDemoModeMiddleware::class)
                    ->name('upload');
            });
        });

    });
});
