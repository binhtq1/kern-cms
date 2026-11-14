<?php

use Illuminate\Support\Facades\Route;
use Kern\AuditLog\Http\Controllers\AuditLogController;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;

AdminHelper::registerRoutes(function (): void {
    Route::middleware(HandleAdminInertiaRequests::class.':maintenance')->group(function (): void {
        Route::group([], function (): void {
            Route::group(['prefix' => 'audit-logs'], function (): void {
                Route::resource('', AuditLogController::class, ['names' => 'audit-log'])->only(['index', 'destroy'])->parameters(['' => 'audit_log'])->middlewareFor('destroy', 'preventDemo');
                Route::get('widgets/activities', [AuditLogController::class, 'getWidgetActivities'])->name('audit-log.widget.activities')->permission('audit-log.index');
                Route::delete('items/empty', [AuditLogController::class, 'deleteAll'])->name('audit-log.empty')->permission('audit-log.destroy')->middleware('preventDemo');
                Route::delete('items/bulk', [AuditLogController::class, 'bulkDestroy'])->name('audit-log.bulk-destroy')->permission('audit-log.destroy')->middleware('preventDemo');
            });
        });

    });
});
