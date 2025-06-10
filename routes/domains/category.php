<?php

use App\Domains\Category\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Category Domain Routes
|--------------------------------------------------------------------------
|
| Rotas para o domínio Category
|
*/

Route::group([
    'prefix' => 'category',
    'middleware' => ['auth:sanctum'],
], function () {

    // Category Routes
    Route::apiResource('categorys', CategoryController::class);


    // Category Routes
    Route::apiResource('categorys', CategoryController::class);
    });
