<?php

use App\Domains\Catalog\Controllers\ProductAdminController;
use App\Domains\Catalog\Controllers\ProductController;
use App\Domains\Catalog\Controllers\ProductPersonalizationOptionAdminController;
use App\Domains\Catalog\Controllers\ProductVariantAdminController;
use App\Domains\Catalog\Controllers\SizeChartAdminController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{id}', [ProductController::class, 'show']);
Route::get('products/{id}/reviews', [ProductController::class, 'reviews']);

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::apiResource('size_charts', SizeChartAdminController::class)
        ->names([
            'index' => 'size.charts.index',
            'store' => 'size.charts.store',
            'show' => 'size.charts.show',
            'update' => 'size.charts.update',
            'destroy' => 'size.charts.destroy',
        ]);

    Route::apiResource('products', ProductAdminController::class);
    Route::apiResource('products.variants', ProductVariantAdminController::class)->scoped();
    // `personalization-options` gera parâmetro inválido no singular (optiom); usar `options`.
    Route::apiResource('products.options', ProductPersonalizationOptionAdminController::class)->scoped();
});
