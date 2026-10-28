<?php

use Illuminate\Support\Facades\Route;
use Kern\Blog\Http\Controllers\API\CategoryController;
use Kern\Blog\Http\Controllers\API\PostController;
use Kern\Blog\Http\Controllers\API\TagController;

Route::group(['middleware' => 'api', 'prefix' => 'api/v1'], function (): void {
    Route::get('search', [PostController::class, 'getSearch']);
    Route::get('posts', [PostController::class, 'index']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('tags', [TagController::class, 'index']);
    Route::get('posts/filters', [PostController::class, 'getFilters']);
    Route::get('posts/{slug}', [PostController::class, 'findBySlug']);
    Route::get('categories/filters', [CategoryController::class, 'getFilters']);
    Route::get('categories/{slug}', [CategoryController::class, 'findBySlug']);
});
