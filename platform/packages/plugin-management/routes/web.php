<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\PluginManagement\Http\Controllers\PluginManagementController;

AdminHelper::registerRoutes(function (): void {
    Route::middleware(HandleAdminInertiaRequests::class.':maintenance')->group(function (): void {
        Route::prefix('plugins')->group(function (): void {
            Route::get('', fn (): RedirectResponse => redirect()->route('plugins.index'));
            Route::get('installed', [PluginManagementController::class, 'index'])->name('plugins.index');
            Route::put('status', [PluginManagementController::class, 'update'])->name('plugins.change.status')->permission('plugins.edit')->middleware('preventDemo');
            Route::delete('{plugin}', [PluginManagementController::class, 'destroy'])->name('plugins.remove')->permission('plugins.remove')->middleware('preventDemo')->where('plugin', '[a-z0-9][a-z0-9_-]*');
            Route::post('check-requirement', [PluginManagementController::class, 'checkRequirement'])->name('plugins.check-requirement')->permission('plugins.edit');
        });

    });
});
