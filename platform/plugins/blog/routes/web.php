<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Supports\AdminContentResource;
use Kern\Blog\Http\Controllers\CategoryController;
use Kern\Blog\Http\Controllers\ExportPostController;
use Kern\Blog\Http\Controllers\ImportPostController;
use Kern\Blog\Http\Controllers\PostController;
use Kern\Blog\Http\Controllers\PublicController;
use Kern\Blog\Http\Controllers\Settings\BlogSettingController;
use Kern\Blog\Http\Controllers\TagController;
use Kern\Theme\Facades\Theme;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'blog'], function (): void {
            Route::group(['prefix' => 'posts', 'middleware' => [HandleAdminInertiaRequests::class.':posts'], 'as' => 'posts.'], function (): void {
                Route::post('bulk', [AdminContentResource::class, 'bulk'])->name('bulk')->permission('posts.index');
                Route::resource('', PostController::class)->parameters(['' => 'post']);
                Route::get('widgets/recent-posts', [PostController::class, 'getWidgetRecentPosts'])->name('widget.recent-posts')->permission('posts.index');
            });
            Route::group(['prefix' => 'categories', 'middleware' => [HandleAdminInertiaRequests::class.':categories'], 'as' => 'categories.'], function (): void {
                Route::post('bulk', [AdminContentResource::class, 'bulk'])->name('bulk')->permission('categories.index');
                Route::resource('', CategoryController::class)->parameters(['' => 'category']);
                Route::put('update-tree', [CategoryController::class, 'updateTree'])->name('update-tree')->permission('categories.index');
            });
            Route::group(['prefix' => 'tags', 'middleware' => [HandleAdminInertiaRequests::class.':tags'], 'as' => 'tags.'], function (): void {
                Route::post('bulk', [AdminContentResource::class, 'bulk'])->name('bulk')->permission('tags.index');
                Route::resource('', TagController::class)->parameters(['' => 'tag']);
                Route::get('all', [TagController::class, 'getAllTags'])->name('all')->permission('tags.index');
            });
            Route::prefix('tools/data-synchronize')->name('tools.data-synchronize.')->middleware(HandleAdminInertiaRequests::class.':operations')->group(function (): void {
                Route::prefix('export')->name('export.')->group(function (): void {
                    Route::group(['prefix' => 'posts', 'as' => 'posts.', 'permission' => 'posts.export'], function (): void {
                        Route::get('/', [ExportPostController::class, 'index'])->name('index');
                        Route::post('/', [ExportPostController::class, 'store'])->name('store');
                    });
                });
                Route::prefix('import')->name('import.')->group(function (): void {
                    Route::group(['prefix' => 'posts', 'as' => 'posts.', 'permission' => 'posts.import'], function (): void {
                        Route::get('/', [ImportPostController::class, 'index'])->name('index');
                        Route::post('/', [ImportPostController::class, 'import'])->name('store');
                        Route::post('validate', [ImportPostController::class, 'validateData'])->name('validate');
                        Route::post('download-example', [ImportPostController::class, 'downloadExample'])->name('download-example');
                    });
                });
            });
        });
        Route::group(['prefix' => 'settings/blog', 'middleware' => HandleAdminInertiaRequests::class.':settings', 'as' => 'blog.settings', 'permission' => 'blog.settings'], function (): void {
            Route::get('/', [BlogSettingController::class, 'edit']);
            Route::put('/', [BlogSettingController::class, 'update'])->name('.update');
        });
    });
    if (defined('THEME_MODULE_SCREEN_NAME')) {
        Theme::registerRoutes(function (): void {
            Route::get('search', [PublicController::class, 'getSearch'])->name('public.search');
        });
    }
});
