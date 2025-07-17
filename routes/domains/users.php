<?php

use App\Domains\Auth\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'verified'])->group(function () {
    /*
     * User
    */
    Route::apiResource('users', UserController::class);
    Route::group(['prefix' => 'users', 'as' => 'users'], function () {
        Route::post('search', [UserController::class, 'search']);
        Route::get('list/roles', [UserController::class, 'roles']);
    });
});
