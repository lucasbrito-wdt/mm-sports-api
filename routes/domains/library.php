<?php

use App\Domains\Library\Controllers\BookController;
use App\Domains\Library\Controllers\BookLoanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Library Domain Routes
|--------------------------------------------------------------------------
|
| Rotas para o domínio Library
|
*/

Route::group([
    'prefix' => 'library',
    'middleware' => ['auth:sanctum'],
], function () {

    // Book Routes
    Route::apiResource('books', BookController::class);


    // Book Routes
    Route::apiResource('books', BookController::class);
    
    // BookLoan Routes
    Route::apiResource('book-loans', BookLoanController::class);
    });
