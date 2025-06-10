<?php

use App\Domains\TestFinal\Controllers\MainModelController;
use App\Domains\TestFinal\Controllers\SubModelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TestFinal Domain Routes
|--------------------------------------------------------------------------
|
| Rotas para o domínio TestFinal
|
*/

Route::group([
    'prefix' => 'test-final',
    'middleware' => ['auth:sanctum'],
], function () {

    // MainModel Routes
    Route::apiResource('main-modeis', MainModelController::class);


    // MainModel Routes
    Route::apiResource('main-modeis', MainModelController::class);
    
    // SubModel Routes
    Route::apiResource('sub-modeis', SubModelController::class);
    });
