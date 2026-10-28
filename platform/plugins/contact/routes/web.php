<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Supports\AdminContentResource;
use Kern\Contact\Http\Controllers\ContactController;
use Kern\Contact\Http\Controllers\CustomFieldController;
use Kern\Contact\Http\Controllers\PublicController;
use Kern\Contact\Http\Controllers\Settings\ContactSettingController;
use Kern\Theme\Facades\Theme;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'contacts', 'as' => 'contacts.'], function (): void {
            Route::post('export', [ContactController::class, 'export'])->middleware(HandleAdminInertiaRequests::class.':contacts')->name('export')->permission('contacts.index');
            Route::post('bulk', [AdminContentResource::class, 'bulk'])->middleware(HandleAdminInertiaRequests::class.':contacts')->name('bulk')->permission('contacts.index');
            Route::resource('', ContactController::class)->middleware(HandleAdminInertiaRequests::class.':contacts')->except(['create', 'store'])->parameters(['' => 'contact']);
            Route::group(['prefix' => 'custom-fields', 'middleware' => [HandleAdminInertiaRequests::class.':contact-fields'], 'as' => 'custom-fields.', 'permission' => 'contact.custom-fields'], function (): void {
                Route::post('bulk', [AdminContentResource::class, 'bulk'])->name('bulk')->permission('contact.custom-fields');
                Route::resource('', CustomFieldController::class)->parameters(['' => 'custom-field']);
            });
            Route::post('reply/{contact}', [ContactController::class, 'postReply'])->middleware(HandleAdminInertiaRequests::class.':contacts')->name('reply')->permission('contacts.edit')->wherePrimaryKey();
        });
        Route::group(['prefix' => 'settings', 'middleware' => HandleAdminInertiaRequests::class.':settings'], function (): void {
            Route::get('contact', [ContactSettingController::class, 'edit'])->name('contact.settings');
            Route::put('contact', [ContactSettingController::class, 'update'])->name('contact.settings.update')->permission('contact.settings');
        });
    });
    if (defined('THEME_MODULE_SCREEN_NAME')) {
        Theme::registerRoutes(function (): void {
            Route::post('contact/send', [PublicController::class, 'postSendContact'])->name('public.send.contact');
        });
    }
});
