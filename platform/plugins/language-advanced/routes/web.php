<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\LanguageAdvanced\Http\Controllers\LanguageAdvancedController;

AdminHelper::registerRoutes(function (): void {
    Route::group([
        'controller' => LanguageAdvancedController::class,
        'prefix' => 'language-advanced',
    ], function (): void {
        Route::post('save/{id}', [
            'as' => 'language-advanced.save',
            'uses' => 'save',
            'permission' => false,
        ])->wherePrimaryKey();
    });
});
