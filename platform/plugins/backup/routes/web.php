<?php

use Illuminate\Support\Facades\Route;
use Kern\Backup\Http\Controllers\BackupController;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::middleware(HandleAdminInertiaRequests::class.':maintenance')->group(function (): void {
            Route::group(['prefix' => 'system/backups'], function (): void {
                Route::get('', [BackupController::class, 'getIndex'])->name('backups.index');
                Route::post('create', [BackupController::class, 'store'])->name('backups.create')->middleware('preventDemo');
                Route::delete('delete/{folder}', [BackupController::class, 'destroy'])->name('backups.destroy')->middleware('preventDemo');
                Route::post('restore/{folder}', [BackupController::class, 'getRestore'])->name('backups.restore')->middleware('preventDemo');
                Route::get('download-database/{folder}', [BackupController::class, 'getDownloadDatabase'])->name('backups.download.database')->permission('backups.index')->middleware('preventDemo');
                Route::get('download-uploads-folder/{folder}', [BackupController::class, 'getDownloadUploadFolder'])->name('backups.download.uploads.folder')->permission('backups.index')->middleware('preventDemo');
            });

        });
    });
});
