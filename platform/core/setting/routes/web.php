<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Setting\Http\Controllers\AdminAppearanceSettingController;
use Kern\Setting\Http\Controllers\CacheSettingController;
use Kern\Setting\Http\Controllers\DataTableSettingController;
use Kern\Setting\Http\Controllers\EmailRuleSettingController;
use Kern\Setting\Http\Controllers\EmailSettingController;
use Kern\Setting\Http\Controllers\EmailTemplateController;
use Kern\Setting\Http\Controllers\EmailTemplateIframeController;
use Kern\Setting\Http\Controllers\EmailTemplatePreviewController;
use Kern\Setting\Http\Controllers\EmailTemplateRestoreController;
use Kern\Setting\Http\Controllers\EmailTemplateSettingController;
use Kern\Setting\Http\Controllers\EmailTemplateStatusController;
use Kern\Setting\Http\Controllers\EmailTestController;
use Kern\Setting\Http\Controllers\GeneralSettingController;
use Kern\Setting\Http\Controllers\HomeSettingController;
use Kern\Setting\Http\Controllers\MediaSettingController;
use Kern\Setting\Http\Middleware\PreserveAdminSettingSecrets;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::middleware(HandleAdminInertiaRequests::class.':settings')->group(function (): void {
            Route::middleware(PreserveAdminSettingSecrets::class)->group(function (): void {
                Route::prefix('settings')->name('settings.')->group(function (): void {
                    Route::get('/', [HomeSettingController::class, 'index'])->name('index');
                    Route::get('options', [HomeSettingController::class, 'index'])->name('options')->permission('settings.index');
                    Route::prefix('general')->group(function (): void {
                        Route::get('/', [GeneralSettingController::class, 'edit'])->name('general')->permission('settings.options');
                        Route::put('/', [GeneralSettingController::class, 'update'])->name('general.update')->permission('settings.options');
                    });
                    Route::prefix('admin-appearance')->group(function (): void {
                        Route::get('/', [AdminAppearanceSettingController::class, 'index'])->name('admin-appearance');
                        Route::put('/', [AdminAppearanceSettingController::class, 'update'])->name('admin-appearance.update')->permission('settings.admin-appearance');
                    });
                    Route::prefix('cache')->group(function (): void {
                        Route::get('/', [CacheSettingController::class, 'edit'])->name('cache');
                        Route::put('cache', [CacheSettingController::class, 'update'])->name('cache.update')->permission('settings.cache');
                    });
                    Route::prefix('datatables')->group(function (): void {
                        Route::get('/', [DataTableSettingController::class, 'edit'])->name('datatables');
                        Route::put('/', [DataTableSettingController::class, 'update'])->name('datatables.update')->permission('settings.datatables');
                    });
                    Route::prefix('media')->group(function (): void {
                        Route::get('/', [MediaSettingController::class, 'edit'])->name('media');
                        Route::put('/', [MediaSettingController::class, 'update'])->name('media.update')->permission('settings.media')->middleware('preventDemo');
                        Route::post('generate-thumbnails', [MediaSettingController::class, 'generateThumbnails'])->name('media.generate-thumbnails')->permission('settings.media')->middleware('preventDemo');
                    });
                    Route::group(['prefix' => 'email', 'permission' => 'settings.email'], function (): void {
                        Route::get('/', [EmailSettingController::class, 'edit'])->name('email');
                        Route::put('/', [EmailSettingController::class, 'update'])->name('email.update')->middleware('preventDemo');
                        Route::post('test/send', [EmailTestController::class, '__invoke'])->name('email.test.send');
                        Route::prefix('templates')->name('email.')->group(function (): void {
                            Route::get('/', [EmailTemplateSettingController::class, 'index'])->name('template');
                            Route::put('', [EmailTemplateSettingController::class, 'update'])->name('template.update-settings');
                            Route::prefix('{type}/{module}/{template}')->name('template.')->group(function (): void {
                                Route::post('status', [EmailTemplateStatusController::class, '__invoke'])->name('status.update')->middleware('preventDemo');
                                Route::get('edit', [EmailTemplateController::class, 'index'])->name('edit');
                                Route::put('/', [EmailTemplateController::class, 'update'])->name('update')->middleware('preventDemo');
                                Route::post('restore', [EmailTemplateRestoreController::class, '__invoke'])->name('restore')->middleware('preventDemo');
                                Route::match(['POST', 'GET'], 'preview', [EmailTemplatePreviewController::class, '__invoke'])->name('preview');
                                Route::get('iframe', [EmailTemplateIframeController::class, '__invoke'])->name('iframe');
                            });
                        });
                        Route::get('rules', [EmailRuleSettingController::class, 'edit'])->name('email.rules');
                        Route::put('rules', [EmailRuleSettingController::class, 'update'])->name('rules.update')->permission('settings.email.rules');
                    });
                });
            });

        });
    });
});
