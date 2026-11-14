<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Supports\AdminContentResource;
use Kern\Block\Http\Controllers\BlockController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'blocks', 'middleware' => [HandleAdminInertiaRequests::class.':blocks'], 'as' => 'block.'], function (): void {
            Route::post('bulk', [AdminContentResource::class, 'bulk'])->name('bulk')->permission('block.index');
            Route::resource('', BlockController::class)->parameters(['' => 'block']);
        });
    });
});
