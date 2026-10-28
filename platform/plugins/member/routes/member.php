<?php

use Illuminate\Support\Facades\Route;
use Kern\Member\Http\Controllers\ForgotPasswordController;
use Kern\Member\Http\Controllers\LoginController;
use Kern\Member\Http\Controllers\PostController;
use Kern\Member\Http\Controllers\PublicController;
use Kern\Member\Http\Controllers\RegisterController;
use Kern\Member\Http\Controllers\ResetPasswordController;
use Kern\Member\Http\Middleware\HandleMemberInertiaRequests;
use Kern\Theme\Facades\Theme;

if (defined('THEME_MODULE_SCREEN_NAME')) {
    Theme::registerRoutes(function (): void {
        Route::group(['middleware' => ['web', 'core'], 'as' => 'public.member.'], function (): void {
            Route::group(['middleware' => ['member.guest']], function (): void {
                Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
                Route::post('login', [LoginController::class, 'login'])->name('login.post');
                Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
                Route::post('register', [RegisterController::class, 'register'])->name('register.post');
                Route::get('password/request', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
                Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
                Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');
                Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
            });
            Route::group(['middleware' => [setting('verify_account_email', config('plugins.member.general.verify_email')) ? 'member.guest' : 'member']], function (): void {
                Route::get('register/confirm/resend', [RegisterController::class, 'resendConfirmation'])->name('resend_confirmation');
                Route::get('register/confirm/{user}', [RegisterController::class, 'confirm'])->name('confirm');
            });
        });
        Route::group(['middleware' => ['web', 'core', 'member', HandleMemberInertiaRequests::class], 'as' => 'public.member.'], function (): void {
            Route::group(['prefix' => 'account'], function (): void {
                Route::match(['GET', 'POST'], 'logout', [LoginController::class, 'logout'])->name('logout');
                Route::get('dashboard', [PublicController::class, 'getDashboard'])->name('dashboard');
                Route::get('settings', [PublicController::class, 'getSettings'])->name('settings');
                Route::post('settings', [PublicController::class, 'postSettings'])->name('post.settings');
                Route::put('security', [PublicController::class, 'postSecurity'])->name('post.security');
                Route::post('avatar', [PublicController::class, 'postAvatar'])->name('avatar');
            });
            Route::group(['prefix' => 'ajax/members'], function (): void {
                Route::get('activity-logs', [PublicController::class, 'getActivityLogs'])->name('activity-logs');
                Route::post('upload', [PublicController::class, 'postUpload'])->name('upload');
                Route::post('picker-upload', [PublicController::class, 'postPickerUpload'])->name('picker-upload');
                Route::post('upload-from-editor', [PublicController::class, 'postUploadFromEditor'])->name('upload-from-editor');
            });
            if (is_plugin_active('blog')) {
                Route::group(['prefix' => 'account/posts', 'as' => 'posts.'], function (): void {
                    Route::post('bulk-delete', [PostController::class, 'bulkDestroy'])->name('bulk-delete');
                    Route::post('bulk-update', [PostController::class, 'bulkUpdate'])->name('bulk-update');
                    Route::resource('', PostController::class)->parameters(['' => 'post']);
                });
                Route::group(['prefix' => 'ajax/members'], function (): void {
                    Route::get('tags/all', [PostController::class, 'getAllTags'])->name('tags.all');
                });
            }
        });
    });
}
