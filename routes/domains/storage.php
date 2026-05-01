<?php

use App\Domains\Storage\Controllers\GeneralUploadPresignController;
use App\Domains\Storage\Controllers\UploadPresignController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::post('products/{product}/uploads/presign', UploadPresignController::class)
        ->middleware(['throttle:20,1']);

    Route::post('uploads/presign', GeneralUploadPresignController::class)
        ->middleware(['throttle:20,1']);
});
