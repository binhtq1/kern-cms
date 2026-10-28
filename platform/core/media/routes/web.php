<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Media\Http\Controllers\MediaController;
use Kern\Media\Http\Controllers\MediaFileController;
use Kern\Media\Http\Controllers\MediaFolderController;
use Kern\Media\Http\Controllers\PublicMediaController;
use Kern\Media\Http\Middleware\EnsureAdminMediaAccess;

Route::group([], function (): void {
    Route::get('media/files/{hash}/{id}', [PublicMediaController::class, 'show'])->name('media.indirect.url')->middleware(['web', 'throttle']);
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'media', 'as' => 'media.', 'permission' => 'media.index', 'middleware' => [EnsureAdminMediaAccess::class, HandleAdminInertiaRequests::class.':media']], function (): void {
            Route::get('', [MediaController::class, 'getMedia'])->name('index');
            Route::get('folder-picker', [MediaController::class, 'getFolderPicker'])->name('folder-picker');
            Route::get('picker', [MediaController::class, 'getPicker'])->name('picker');
            Route::post('picker-upload', [MediaController::class, 'uploadPicker'])->name('picker-upload');
            Route::get('popup', [MediaController::class, 'getPopup'])->name('popup');
            Route::get('list', [MediaController::class, 'getList'])->name('list');
            Route::get('breadcrumbs', [MediaController::class, 'getBreadcrumbs'])->name('breadcrumbs');
            Route::post('global-actions', [MediaController::class, 'postGlobalActions'])->name('global_actions');
            Route::post('download', [MediaController::class, 'download'])->name('download');
            Route::group(['prefix' => 'files'], function (): void {
                Route::post('upload', [MediaFileController::class, 'postUpload'])->name('files.upload');
                Route::post('upload-from-editor', [MediaFileController::class, 'postUploadFromEditor'])->name('files.upload.from.editor');
                Route::post('download-url', [MediaFileController::class, 'postDownloadUrl'])->name('download_url');
            });
            Route::group(['prefix' => 'folders'], function (): void {
                Route::post('create', [MediaFolderController::class, 'store'])->name('folders.create');
            });
        });
    });
});
