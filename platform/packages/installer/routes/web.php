<?php

use Illuminate\Support\Facades\Route;
use Kern\Installer\Http\Controllers\AccountController;
use Kern\Installer\Http\Controllers\EnvironmentController;
use Kern\Installer\Http\Controllers\FinalController;
use Kern\Installer\Http\Controllers\InstallController;
use Kern\Installer\Http\Controllers\RequirementController;
use Kern\Installer\Http\Controllers\ThemeController;

Route::prefix('install')->name('installers.')->middleware('web')->group(function (): void {
    Route::middleware('install')->group(function (): void {
        Route::get('welcome', [InstallController::class, 'index'])->name('welcome');
        Route::post('welcome/next', [InstallController::class, 'next'])->name('welcome.next');
        Route::get('requirements', [RequirementController::class, 'index'])->name('requirements.index');
        Route::get('environments', [EnvironmentController::class, 'index'])->name('environments.index');
        Route::post('environments', [EnvironmentController::class, 'store'])->name('environments.store')->middleware('signed');
    });

    Route::middleware(['installing', 'signed'])->group(function (): void {
        Route::get('themes', [ThemeController::class, 'index'])->name('themes.index');
        Route::post('themes', [ThemeController::class, 'store'])->name('themes.store');
        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('final', [FinalController::class, 'index'])->name('final');
    });
});
