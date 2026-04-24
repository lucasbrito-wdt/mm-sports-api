<?php

use App\Domains\Marketing\Controllers\BannerAdminController;
use App\Domains\Marketing\Controllers\BannerController;
use App\Domains\Marketing\Controllers\PromotionAdminController;
use Illuminate\Support\Facades\Route;

Route::get('banners', [BannerController::class, 'index']);

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::apiResource('banners', BannerAdminController::class);
    Route::apiResource('promotions', PromotionAdminController::class);
});
