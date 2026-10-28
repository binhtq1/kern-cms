<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Page\Http\Controllers\PageController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'pages', 'as' => 'pages.'], function (): void {
            Route::middleware(HandleAdminInertiaRequests::class)->group(function (): void {
                Route::post('media-upload', [PageController::class, 'uploadMedia'])->name('media-upload')->permission('media.index');
                Route::get('media-picker', [PageController::class, 'media'])->name('media')->permission('media.index');
                Route::post('bulk', [PageController::class, 'bulk'])->name('bulk')->permission('pages.index');
                Route::resource('', PageController::class)->parameters(['' => 'page']);
            });
        });
    });
});
