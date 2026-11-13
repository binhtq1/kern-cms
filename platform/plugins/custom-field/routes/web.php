<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\CustomField\Http\Controllers\CustomFieldController;
use Kern\CustomField\Support\AdminFieldGroupData;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['middleware' => [HandleAdminInertiaRequests::class.':field-groups'], 'prefix' => 'custom-fields', 'as' => 'custom-fields.'], function (): void {
            Route::post('bulk', [AdminFieldGroupData::class, 'bulk'])->name('bulk')->permission('custom-fields.index')->middleware('preventDemo');
            Route::resource('', CustomFieldController::class)->parameters(['' => 'custom-field']);
            Route::get('export/{id?}', [CustomFieldController::class, 'getExport'])->name('export')->permission('custom-fields.index');
            Route::post('import', [CustomFieldController::class, 'postImport'])->name('import')->permission('custom-fields.index');
        });
    });
});
