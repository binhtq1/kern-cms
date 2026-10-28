<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Dashboard\Http\Controllers\DashboardController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::middleware(HandleAdminInertiaRequests::class.':dashboard')->group(function (): void {
            Route::get('', [DashboardController::class, 'getDashboard'])->name('dashboard.index')->permission(false);
            Route::group(['prefix' => 'widgets', 'permission' => false], function (): void {
                Route::get('hide', [DashboardController::class, 'getHideWidget'])->name('dashboard.hide_widget');
                Route::post('hides', [DashboardController::class, 'postHideWidgets'])->name('dashboard.hide_widgets');
                Route::post('order', [DashboardController::class, 'postUpdateWidgetOrder'])->name('dashboard.update_widget_order');
                Route::post('setting-item', [DashboardController::class, 'postEditWidgetSettingItem'])->name('dashboard.edit_widget_setting_item');
            });

        });
    });
});
