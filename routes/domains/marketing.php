<?php

use App\Domains\Marketing\Controllers\BannerAdminController;
use App\Domains\Marketing\Controllers\BannerController;
use App\Domains\Marketing\Controllers\CouponAdminController;
use App\Domains\Marketing\Controllers\CouponController;
use App\Domains\Marketing\Controllers\PromotionAdminController;
use Illuminate\Support\Facades\Route;

Route::get('banners', [BannerController::class, 'index']);
Route::post('coupons/validate', [CouponController::class, 'validate']);

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::apiResource('banners', BannerAdminController::class);
    Route::apiResource('promotions', PromotionAdminController::class);
    Route::get('coupons/{coupon}/metrics', [CouponAdminController::class, 'metrics']);
    Route::apiResource('coupons', CouponAdminController::class)->parameters(['coupons' => 'coupon']);
});
