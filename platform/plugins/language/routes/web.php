<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Language\Http\Controllers\LanguageController;
use Kern\Language\Http\Controllers\Settings\LanguageSettingController;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'settings/languages', 'middleware' => [HandleAdminInertiaRequests::class.':languages']], function (): void {
            Route::get('', [LanguageController::class, 'index'])->name('languages.index');
            Route::get('options', [LanguageController::class, 'index'])->name('settings.language')->permission('languages.index');
            Route::post('store', [LanguageController::class, 'store'])->name('languages.store')->permission('languages.create')->middleware('preventDemo');
            Route::post('edit', [LanguageController::class, 'update'])->name('languages.edit')->middleware('preventDemo');
            Route::delete('delete/{id}', [LanguageController::class, 'destroy'])->name('languages.destroy')->middleware('preventDemo')->wherePrimaryKey();
            Route::post('set-default', [LanguageController::class, 'getSetDefault'])->name('languages.set.default.update')->permission('languages.edit')->middleware('preventDemo');
            Route::get('set-default', [LanguageController::class, 'getSetDefault'])->name('languages.set.default')->permission('languages.edit');
            Route::get('get', [LanguageController::class, 'getLanguage'])->name('languages.get')->permission('languages.edit');
            Route::post('edit-setting', [LanguageSettingController::class, 'update'])->name('languages.settings')->permission('languages.edit');
        });
    });
    Route::group(['prefix' => 'languages'], function (): void {
        Route::post('change-item-language', [LanguageController::class, 'postChangeItemLanguage'])->name('languages.change.item.language')->permission(false);
        Route::get('change-data-language/{locale}', [LanguageController::class, 'getChangeDataLanguage'])->name('languages.change.data.language')->permission(false);
    });
});
