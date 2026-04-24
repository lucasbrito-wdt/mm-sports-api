<?php

use App\Domains\Commerce\Controllers\CheckoutQuoteController;
use App\Domains\Commerce\Controllers\DashboardAdminController;
use App\Domains\Commerce\Controllers\OrderAdminController;
use App\Domains\Commerce\Controllers\OrderController;
use App\Domains\Commerce\Controllers\UserAddressController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardAdminController::class, 'summary'])->name('dashboard.summary');
    Route::get('orders', [OrderAdminController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderAdminController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}', [OrderAdminController::class, 'update'])->name('orders.update');
});

Route::middleware('auth:api')->group(function () {
    Route::post('checkout/quote', [CheckoutQuoteController::class, 'quote']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'place']);
    Route::get('orders/{id}/timeline', [OrderController::class, 'timeline']);
    Route::get('orders/{id}', [OrderController::class, 'show']);

    Route::get('user-addresses', [UserAddressController::class, 'index']);
    Route::post('user-addresses', [UserAddressController::class, 'create']);
    Route::get('user-addresses/{id}', [UserAddressController::class, 'show']);
    Route::put('user-addresses/{id}', [UserAddressController::class, 'updateAddress']);
    Route::delete('user-addresses/{id}', [UserAddressController::class, 'destroy']);
});
