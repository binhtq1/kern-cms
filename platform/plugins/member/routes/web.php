<?php

use Illuminate\Support\Facades\Route;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\Base\Supports\AdminContentResource;
use Kern\Member\Http\Controllers\MemberController;
use Kern\Member\Http\Controllers\PublicController;
use Kern\Member\Http\Controllers\Settings\MemberSettingController;
use Kern\Member\Models\Member;
use Kern\Slug\Facades\SlugHelper;
use Kern\Theme\Facades\Theme;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['prefix' => 'members', 'middleware' => [HandleAdminInertiaRequests::class.':members'], 'as' => 'member.'], function (): void {
            Route::post('bulk', [AdminContentResource::class, 'bulk'])->name('bulk')->permission('member.index');
            Route::resource('', MemberController::class)->parameters(['' => 'member']);
            Route::post('verify-email/{id}', [MemberController::class, 'verifyEmail'])->name('verify-email')->permission('member.edit')->wherePrimaryKey();
        });
        Route::group(['prefix' => 'settings', 'middleware' => HandleAdminInertiaRequests::class.':settings', 'as' => 'member.'], function (): void {
            Route::get('members', [MemberSettingController::class, 'edit'])->name('settings');
            Route::put('members', [MemberSettingController::class, 'update'])->name('settings.update')->permission('member.settings');
        });
    });
    if (defined('THEME_MODULE_SCREEN_NAME')) {
        Theme::registerRoutes(function (): void {
            Route::get(SlugHelper::getPrefix(Member::class, 'author').'/{slug}', [PublicController::class, 'getAuthor'])->name('author.show');
        });
    }
});
