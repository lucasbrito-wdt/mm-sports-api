<?php

use App\Domains\Auth\Controllers\CustomerAddressAdminController;
use App\Domains\Auth\Controllers\CustomerAdminController;
use App\Domains\Auth\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'verified'])->group(function () {
    /*
     * User
    */
    Route::apiResource('users', UserController::class);
    Route::group(['prefix' => 'users', 'as' => 'users'], function () {
        Route::post('search', [UserController::class, 'searchText']);
        Route::get('list/roles', [UserController::class, 'roles']);
    });
});

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('customers', [CustomerAdminController::class, 'index']);
    Route::get('customers/{customer}', [CustomerAdminController::class, 'show']);
    Route::patch('customers/{customer}', [CustomerAdminController::class, 'update']);
    Route::get('customers/{customer}/addresses', [CustomerAddressAdminController::class, 'list']);
    Route::post('customers/{customer}/addresses', [CustomerAddressAdminController::class, 'store']);
    Route::put('customers/{customer}/addresses/{address}', [CustomerAddressAdminController::class, 'update']);
    Route::delete('customers/{customer}/addresses/{address}', [CustomerAddressAdminController::class, 'destroy']);
});
