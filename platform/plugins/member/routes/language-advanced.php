<?php

use Illuminate\Support\Facades\Route;
use Kern\LanguageAdvanced\Http\Controllers\LanguageAdvancedController;

Route::group([
    'controller' => LanguageAdvancedController::class,
    'middleware' => ['web', 'core', 'member'],
    'prefix' => 'account',
    'as' => 'public.member.language-advanced.',
], function (): void {
    Route::post('language-advanced/save/{id}', ['as' => 'save', 'uses' => 'save'])->wherePrimaryKey();
});
