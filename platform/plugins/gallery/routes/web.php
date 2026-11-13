<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Supports\AdminContentResource;
use Kern\Gallery\Http\Controllers\GalleryController;
use Kern\Gallery\Http\Controllers\PublicController;
use Kern\Gallery\Models\Gallery;
use Kern\Slug\Facades\SlugHelper;
use Kern\Theme\Facades\Theme;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'galleries', 'middleware' => [HandleAdminInertiaRequests::class.':galleries'], 'as' => 'galleries.'], function (): void {
            Route::post('bulk', [AdminContentResource::class, 'bulk'])->name('bulk')->permission('galleries.index');
            Route::resource('', GalleryController::class)->parameters(['' => 'gallery']);
        });
    });
    if (defined('THEME_MODULE_SCREEN_NAME')) {
        Theme::registerRoutes(function (): void {
            if (! theme_option('galleries_page_id')) {
                $prefix = SlugHelper::getPrefix(Gallery::class, 'galleries');
                Route::get($prefix ?: 'galleries', [PublicController::class, 'getGalleries'])->name('public.galleries');
            }
        });
    }
});
