<?php

use App\Domains\BlogComplete\Controllers\PostController;
use App\Domains\BlogComplete\Controllers\CommentController;
use App\Domains\BlogComplete\Controllers\TagController;
use App\Domains\BlogComplete\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BlogComplete Domain Routes
|--------------------------------------------------------------------------
|
| Rotas para o domínio BlogComplete
|
*/

Route::group([
    'prefix' => 'blog-complete',
    'middleware' => ['auth:sanctum'],
], function () {

    // Post Routes
    Route::apiResource('posts', PostController::class);

    // Comment Routes
    Route::apiResource('comments', CommentController::class);

    // Tag Routes
    Route::apiResource('tags', TagController::class);

    // Category Routes
    Route::apiResource('categorys', CategoryController::class);
});
