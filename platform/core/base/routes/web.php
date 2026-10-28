<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Controllers\CacheManagementController;
use Kern\Base\Http\Controllers\CoreIconController;
use Kern\Base\Http\Controllers\CronjobSettingController;
use Kern\Base\Http\Controllers\DebugModeController;
use Kern\Base\Http\Controllers\NotificationController;
use Kern\Base\Http\Controllers\SearchController;
use Kern\Base\Http\Controllers\SystemController;
use Kern\Base\Http\Controllers\SystemInformationController;
use Kern\Base\Http\Controllers\ToggleThemeModeController;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Http\Middleware\RequiresJsonRequestMiddleware;
use Kern\Base\Supports\AdminSessionContext;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::middleware(HandleAdminInertiaRequests::class.':system')->group(function (): void {
            Route::group(['prefix' => 'system'], function (): void {
                Route::get('', [SystemController::class, 'getIndex'])->name('system.index')->permission('core.system');
            });
            Route::group(['permission' => 'superuser'], function (): void {
                Route::prefix('system/info')->group(function (): void {
                    Route::match(['GET', 'POST'], '/', [SystemInformationController::class, 'index'])->name('system.info');
                    Route::get('get-addition-data', [SystemInformationController::class, 'getAdditionData'])->middleware(RequiresJsonRequestMiddleware::class)->name('system.info.get-addition-data');
                });
                Route::prefix('system/cache')->group(function (): void {
                    Route::get('', [CacheManagementController::class, 'index'])->name('system.cache');
                    Route::post('clear', [CacheManagementController::class, 'destroy'])->name('system.cache.clear')->middleware('preventDemo');
                });
            });
            Route::get('system/cleanup', [SystemController::class, 'getCleanup'])->name('system.cleanup')->permission('superuser');
            Route::post('system/cleanup', [SystemController::class, 'getCleanup'])->name('system.cleanup.process')->permission('superuser')->middleware('preventDemo');
            Route::post('system/debug-mode/turn-off', [DebugModeController::class, 'postTurnOff'])->name('system.debug-mode.turn-off')->permission('superuser')->middleware('preventDemo');
            Route::get('system/cronjob', [CronjobSettingController::class, 'index'])->name('system.cronjob');
            Route::group(['permission' => false], function (): void {
                Route::get('session/status', fn (Request $request) => response()->json(['matches' => AdminSessionContext::matches($request)], 200, ['Cache-Control' => 'no-store']))->name('admin.session-status');
                Route::get('menu-items-count', [SystemController::class, 'getMenuItemsCount'])->name('menu-items-count');
                Route::group(['prefix' => 'notifications', 'as' => 'notifications.', 'controller' => NotificationController::class], function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::delete('{id}', 'destroy')->name('destroy')->wherePrimaryKey();
                    Route::get('read-notification/{id}', 'read')->name('read-notification')->wherePrimaryKey();
                    Route::put('read-all-notification', 'readAll')->name('read-all-notification');
                    Route::delete('destroy-all-notification', 'deleteAll')->name('destroy-all-notification');
                    Route::get('count-unread', 'countUnread')->name('count-unread');
                });
                Route::get('toggle-theme-mode', [ToggleThemeModeController::class, '__invoke'])->name('toggle-theme-mode');
                Route::get('search', [SearchController::class, '__invoke'])->name('core.global-search');
                Route::get('core-icons', [CoreIconController::class, 'index'])->name('core-icons')->middleware(RequiresJsonRequestMiddleware::class);
            });

        });
    });
});
