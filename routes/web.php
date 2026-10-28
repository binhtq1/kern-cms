<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Kern\Theme\Facades\Theme;

if (! Theme::getThemeName()) {
    Route::get('/', HomeController::class)->name('home');
}
