<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Widget\Http\Controllers\WidgetController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'widgets', 'middleware' => [HandleAdminInertiaRequests::class.':widgets']], function (): void {
            Route::get('', [WidgetController::class, 'index'])->name('widgets.index');
            Route::post('save-all', [WidgetController::class, 'saveAll'])->name('widgets.save_all')->permission('widgets.index');
            Route::post('save-widgets-to-sidebar', [WidgetController::class, 'update'])->name('widgets.save_widgets_sidebar')->permission('widgets.index');
            Route::delete('delete', [WidgetController::class, 'destroy'])->name('widgets.destroy')->permission('widgets.index');
        });
    });
});
