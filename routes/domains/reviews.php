<?php

use App\Domains\Reviews\Controllers\ProductReviewAdminController;
use App\Domains\Reviews\Controllers\ProductReviewController;
use App\Domains\Reviews\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('product-reviews', [ProductReviewAdminController::class, 'index'])
        ->name('product.reviews_moderation.index');
    Route::get('product-reviews/{product_review}', [ProductReviewAdminController::class, 'show'])
        ->name('product.reviews_moderation.show');
    Route::patch('product-reviews/{product_review}', [ProductReviewAdminController::class, 'update'])
        ->name('product.reviews_moderation.update');
});

Route::middleware('auth:api')->group(function () {
    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist', [WishlistController::class, 'addToWishlist']);
    Route::delete('wishlist/{product_variant_id}', [WishlistController::class, 'removeFromWishlist']);

    Route::post('reviews', [ProductReviewController::class, 'createReview']);
});
