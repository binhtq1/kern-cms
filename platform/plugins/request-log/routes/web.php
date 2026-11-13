<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\RequestLog\Http\Controllers\RequestLogController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::middleware(HandleAdminInertiaRequests::class.':maintenance')->group(function (): void {
            Route::group(['prefix' => 'request-logs', 'as' => 'request-log.'], function (): void {
                Route::resource('', RequestLogController::class)->only(['index', 'destroy'])->parameters(['' => 'request-log'])->middlewareFor('destroy', 'preventDemo');
                Route::get('widgets/request-errors', [RequestLogController::class, 'getWidgetRequestErrors'])->name('widget.request-errors')->permission('request-log.index');
                Route::delete('items/empty', [RequestLogController::class, 'deleteAll'])->name('empty')->permission('request-log.destroy')->middleware('preventDemo');
                Route::delete('items/bulk', [RequestLogController::class, 'bulkDestroy'])->name('bulk-destroy')->permission('request-log.destroy')->middleware('preventDemo');
            });

        });
    });
});
