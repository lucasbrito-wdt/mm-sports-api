<?php

use App\Domains\Catalog\Controllers\Admin\AttributeController;
use App\Domains\Catalog\Controllers\Admin\AttributeValueController;
use App\Domains\Catalog\Controllers\Admin\CategoryController;
use App\Domains\Catalog\Controllers\Admin\ProductFacetAttributesController;
use App\Domains\Catalog\Controllers\Admin\ProductImageController;
use App\Domains\Catalog\Controllers\Admin\ProductVariantMatrixController;
use App\Domains\Catalog\Controllers\CatalogFacetController;
use App\Domains\Catalog\Controllers\CatalogProductController;
use App\Domains\Catalog\Controllers\ProductAdminController;
use App\Domains\Catalog\Controllers\ProductController;
use App\Domains\Catalog\Controllers\ProductPersonalizationOptionAdminController;
use App\Domains\Catalog\Controllers\ProductVariantAdminController;
use App\Domains\Catalog\Controllers\Public\PublicCategoryController;
use App\Domains\Catalog\Controllers\SizeChartAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->group(function () {
    Route::get('facets', [CatalogFacetController::class, 'index']);
    Route::get('products', [CatalogProductController::class, 'index']);
});

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{id}', [ProductController::class, 'show']);
Route::get('products/{id}/reviews', [ProductController::class, 'reviews']);

Route::get('categories/tree', [PublicCategoryController::class, 'tree']);
Route::get('categories', [PublicCategoryController::class, 'index']);

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::apiResource('size_charts', SizeChartAdminController::class)
        ->names([
            'index' => 'size.charts.index',
            'store' => 'size.charts.store',
            'show' => 'size.charts.show',
            'update' => 'size.charts.update',
            'destroy' => 'size.charts.destroy',
        ]);

    Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
    Route::post('attributes', [AttributeController::class, 'store'])->name('attributes.store');
    Route::put('attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
    Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');

    Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::post('attributes/{attribute}/values', [AttributeValueController::class, 'store'])->name('attributes.values.store');
    Route::put('attributes/values/{value}', [AttributeValueController::class, 'update'])->name('attributes.values.update');
    Route::delete('attributes/values/{value}', [AttributeValueController::class, 'destroy'])->name('attributes.values.destroy');

    Route::apiResource('products', ProductAdminController::class);
    Route::post('products/{product}/variant-matrix', [ProductVariantMatrixController::class, 'generate'])
        ->name('products.variant-matrix.generate');
    Route::apiResource('products.variants', ProductVariantAdminController::class)->scoped();
    // `personalization-options` gera parâmetro inválido no singular (optiom); usar `options`.
    Route::apiResource('products.options', ProductPersonalizationOptionAdminController::class)->scoped();

    Route::prefix('products/{product}')->group(function () {
        Route::get('images', [ProductImageController::class, 'index']);
        Route::post('images', [ProductImageController::class, 'store']);
        Route::put('images/{image}', [ProductImageController::class, 'update']);
        Route::delete('images/{image}', [ProductImageController::class, 'destroy']);

        Route::get('facet-attributes', [ProductFacetAttributesController::class, 'show']);
        Route::put('facet-attributes', [ProductFacetAttributesController::class, 'update']);
    });
});
