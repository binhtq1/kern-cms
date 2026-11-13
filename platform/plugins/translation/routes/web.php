<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Translation\Http\Controllers\ExportOtherTranslationController;
use Kern\Translation\Http\Controllers\ExportThemeTranslationController;
use Kern\Translation\Http\Controllers\ImportOtherTranslationController;
use Kern\Translation\Http\Controllers\ImportThemeTranslationController;
use Kern\Translation\Http\Controllers\LocaleController;
use Kern\Translation\Http\Controllers\ReImportThemeTranslationController;
use Kern\Translation\Http\Controllers\ThemeTranslationController;
use Kern\Translation\Http\Controllers\TranslationController;
use Kern\Translation\Supports\AdminTranslationData;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'translations', 'middleware' => [HandleAdminInertiaRequests::class.':translations']], function (): void {
            Route::get('react-export', [AdminTranslationData::class, 'export'])->name('translations.react.export')->permission(false);
            Route::post('react-import', [AdminTranslationData::class, 'import'])->name('translations.react.import')->permission(false)->middleware('preventDemo');
            Route::group(['prefix' => 'locales', 'permission' => 'translations.locales'], function (): void {
                Route::get('', [LocaleController::class, 'index'])->name('translations.locales');
                Route::post('', [LocaleController::class, 'store'])->name('translations.locales.post')->middleware('preventDemo');
                Route::delete('{locale}', [LocaleController::class, 'destroy'])->name('translations.locales.delete')->middleware('preventDemo');
                Route::get('download/{locale}', [LocaleController::class, 'download'])->name('translations.locales.download')->middleware('preventDemo');
            });
            Route::group(['permission' => 'translations.index'], function (): void {
                Route::match(['GET', 'POST'], '', [TranslationController::class, 'index'])->name('translations.index');
                Route::post('edit', [TranslationController::class, 'update'])->name('translations.group.edit')->middleware('preventDemo');
            });
            Route::group(['prefix' => 'theme', 'permission' => 'translations.theme-translations'], function (): void {
                Route::match(['GET', 'POST'], '', [ThemeTranslationController::class, 'index'])->name('translations.theme-translations');
                Route::post('post', [ThemeTranslationController::class, 'update'])->name('translations.theme-translations.post')->middleware('preventDemo');
                Route::post('re-import', [ReImportThemeTranslationController::class, '__invoke'])->name('translations.theme-translations.re-import')->middleware('preventDemo');
            });
        });
        Route::prefix('tools/data-synchronize')->name('tools.data-synchronize.')->middleware(HandleAdminInertiaRequests::class.':operations')->group(function (): void {
            Route::prefix('export')->name('export.')->group(function (): void {
                Route::group(['prefix' => 'theme-translations/export', 'as' => 'theme-translations.', 'permission' => 'theme-translations.export'], function (): void {
                    Route::get('/', [ExportThemeTranslationController::class, 'index'])->name('index');
                    Route::post('/', [ExportThemeTranslationController::class, 'store'])->name('store');
                });
                Route::group(['prefix' => 'other-translations', 'as' => 'other-translations.', 'permission' => 'other-translations.export'], function (): void {
                    Route::get('/', [ExportOtherTranslationController::class, 'index'])->name('index');
                    Route::post('/', [ExportOtherTranslationController::class, 'store'])->name('store');
                });
            });
            Route::prefix('import')->name('import.')->group(function (): void {
                Route::group(['prefix' => 'theme-translations', 'as' => 'theme-translations.', 'permission' => 'theme-translations.import'], function (): void {
                    Route::get('/', [ImportThemeTranslationController::class, 'index'])->name('index');
                    Route::post('/', [ImportThemeTranslationController::class, 'import'])->name('store');
                    Route::post('validate', [ImportThemeTranslationController::class, 'validateData'])->name('validate');
                });
                Route::group(['prefix' => 'other-translations', 'as' => 'other-translations.', 'permission' => 'other-translations.import'], function (): void {
                    Route::get('/', [ImportOtherTranslationController::class, 'index'])->name('index');
                    Route::post('/', [ImportOtherTranslationController::class, 'import'])->name('store');
                    Route::post('validate', [ImportOtherTranslationController::class, 'validateData'])->name('validate');
                });
            });
        });
    });
});
