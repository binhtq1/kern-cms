<?php

use Illuminate\Support\Facades\Route;
use Kern\ACL\Http\Controllers\Auth\ForgotPasswordController;
use Kern\ACL\Http\Controllers\Auth\LoginController;
use Kern\ACL\Http\Controllers\Auth\ResetPasswordController;
use Kern\ACL\Http\Controllers\RoleController;
use Kern\ACL\Http\Controllers\UserController;
use Kern\ACL\Http\Middleware\CheckUserUpdatePermission;
use Kern\ACL\Supports\AdminAclData;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;

Route::group([], function (): void {
    AdminHelper::registerRoutes(function (): void {
        Route::group(['middleware' => ['guest', HandleAdminInertiaRequests::class.':auth']], function (): void {
            Route::get('login', [LoginController::class, 'showLoginForm'])->name('access.login');
            Route::post('login', [LoginController::class, 'login'])->name('access.login.post');
            Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('access.password.request');
            Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('access.password.email');
            Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('access.password.reset');
            Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('access.password.reset.post');
        });
    }, ['web', 'core']);
    AdminHelper::registerRoutes(function (): void {
        Route::get('logout', [LoginController::class, 'logout'])->name('access.logout')->permission(false);
        Route::group(['prefix' => 'system'], function (): void {
            Route::group(['prefix' => 'users', 'as' => 'users.', 'middleware' => HandleAdminInertiaRequests::class.':users'], function (): void {
                Route::post('bulk', [AdminAclData::class, 'bulkUsers'])->name('bulk')->permission(false)->middleware('preventDemo');
                Route::resource('', UserController::class)->except(['edit', 'update'])->parameters(['' => 'user']);
                Route::post('modify-profile-image/{user}', [UserController::class, 'postAvatar'])->name('profile.image')->permission(false)->wherePrimaryKey();
                Route::post('delete-profile-image/{user}', [UserController::class, 'removeAvatar'])->name('profile.image.destroy')->permission(false)->wherePrimaryKey();
                Route::middleware(CheckUserUpdatePermission::class)->group(function (): void {
                    Route::put('password/{user}', [UserController::class, 'postChangePassword'])->name('change-password')->permission(false)->middleware('preventDemo')->wherePrimaryKey('user');
                    Route::get('profile/{user}', [UserController::class, 'getUserProfile'])->name('profile.view')->permission(false)->wherePrimaryKey('user');
                    Route::put('profile/{user}', [UserController::class, 'postUpdateProfile'])->name('update-profile')->permission(false)->middleware('preventDemo')->wherePrimaryKey('user');
                    Route::put('profile/{user}/preferences', [UserController::class, 'updatePreferences'])->name('update-preferences')->permission(false)->middleware('preventDemo')->wherePrimaryKey('user');
                    Route::patch('profile/{user}/preferences', [UserController::class, 'patchUpdatePreferences'])->permission(false)->wherePrimaryKey('user');
                });
                Route::post('make-super/{user}', [UserController::class, 'makeSuper'])->name('make-super')->permission(ACL_ROLE_SUPER_USER)->middleware('preventDemo')->wherePrimaryKey('user');
                Route::delete('remove-super/{user}', [UserController::class, 'removeSuper'])->name('remove-super')->permission(ACL_ROLE_SUPER_USER)->middleware('preventDemo')->wherePrimaryKey('user');
            });
            Route::group(['prefix' => 'roles', 'as' => 'roles.', 'middleware' => HandleAdminInertiaRequests::class.':roles'], function (): void {
                Route::post('bulk', [AdminAclData::class, 'bulkRoles'])->name('bulk')->permission(false)->middleware('preventDemo');
                Route::resource('', RoleController::class)->parameters(['' => 'role']);
                Route::post('duplicate/{role}', [RoleController::class, 'getDuplicate'])->name('duplicate')->permission('roles.create')->wherePrimaryKey('role');
                Route::get('json', [RoleController::class, 'getJson'])->name('list.json')->permission('roles.index');
                Route::post('assign', [RoleController::class, 'postAssignMember'])->name('assign')->permission('roles.edit');
            });
        });
    });
});
