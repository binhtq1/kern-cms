<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Http\Middleware\RequiresJsonRequestMiddleware;
use Kern\Theme\Facades\Theme;
use Theme\Ripple\Http\Controllers\RippleController;

Theme::registerRoutes(function (): void {
    Route::group(['controller' => RippleController::class], function (): void {
        Route::middleware(RequiresJsonRequestMiddleware::class)
            ->group(function (): void {
                Route::get('ajax/search', 'getSearch')->name('public.ajax.search');
            });

        // Add your custom route here
        // Ex: Route::get('hello', 'getHello');
    });
});

Theme::routes();
