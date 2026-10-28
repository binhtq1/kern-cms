<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Slug\Http\Controllers\SlugController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'settings/permalink', 'middleware' => HandleAdminInertiaRequests::class.':settings'], function (): void {
            Route::get('', [SlugController::class, 'edit'])->name('slug.settings')->permission('settings.options');
            Route::put('', [SlugController::class, 'update'])->name('slug.settings.update')->permission('settings.options')->middleware('preventDemo');
        });
    });
    Route::group(['prefix' => 'ajax/slug', 'middleware' => ['web', 'core']], function (): void {
        Route::post('create', [SlugController::class, 'store'])->name('slug.create');
    });
});
