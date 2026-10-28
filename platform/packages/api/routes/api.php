<?php

use Illuminate\Support\Facades\Route;
use Kern\Api\Http\Controllers\AuthenticationController;
use Kern\Api\Http\Controllers\ForgotPasswordController;
use Kern\Api\Http\Controllers\ProfileController;
use Kern\Api\Http\Controllers\VerificationController;

Route::group(['prefix' => 'api/v1', 'middleware' => ['api']], function () {
    Route::post('register', [AuthenticationController::class, 'register']);
    Route::post('login', [AuthenticationController::class, 'login']);
    Route::post('email/check', [AuthenticationController::class, 'checkEmail']);
    Route::post('password/forgot', [ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('resend-verify-account-email', [VerificationController::class, 'resend']);
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::get('logout', [AuthenticationController::class, 'logout']);
        Route::get('me', [ProfileController::class, 'getProfile']);
        Route::put('me', [ProfileController::class, 'updateProfile']);
        Route::post('update/avatar', [ProfileController::class, 'updateAvatar']);
        Route::put('update/password', [ProfileController::class, 'updatePassword']);
    });
});
