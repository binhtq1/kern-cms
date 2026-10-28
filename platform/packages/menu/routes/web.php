<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Menu\Http\Controllers\MenuController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'menus', 'as' => 'menus.', 'middleware' => [HandleAdminInertiaRequests::class.':menus']], function (): void {
            Route::get('references', [MenuController::class, 'references'])->name('references')->permission(false);
            Route::resource('', MenuController::class)->parameters(['' => 'menu']);
            Route::post('bulk', [MenuController::class, 'bulk'])->name('bulk')->permission('menus.index');
            Route::post('ajax/get-node', [MenuController::class, 'getNode'])->name('get-node')->permission('menus.edit');
        });
    });
});
