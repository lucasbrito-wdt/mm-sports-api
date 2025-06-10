<?php

use App\Domains\Product\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Product Domain Routes
|--------------------------------------------------------------------------
|
| Rotas para o domínio Product
|
*/

Route::group([
    'prefix' => 'product',
    'middleware' => ['auth:sanctum'],
], function () {

    // Product Routes
    Route::apiResource('products', ProductController::class);


    // Product Routes
    Route::apiResource('products', ProductController::class);
    });
