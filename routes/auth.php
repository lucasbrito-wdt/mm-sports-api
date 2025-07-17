<?php

use App\Domains\Auth\Controllers\EmailVerificationNotificationController;
use App\Domains\Auth\Controllers\EmailVerificationPromptController;
use App\Domains\Auth\Controllers\VerifyEmailController;
use App\Domains\Auth\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'auth', 'as' => 'auth', 'controller' => AuthController::class], function () {
    Route::post('login', 'login');
    Route::post('register', 'register');

    Route::post('forgot-password', 'forgotPassword')->name('forgot.password');
    Route::post('reset-password', 'resetPassword')->name('password.reset');

    Route::prefix('email')->group(function () {
        Route::get('verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('verify-email/{email}', VerifyEmailController::class)
            ->middleware('throttle:6,1')
            ->name('verification.verify')
            ->where('email', '[a-zA-Z0-9_\-\.\+]+');

        Route::post('verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });
});

Route::group([
    'prefix' => 'auth',
    'as' => 'auth',
    'middleware' => ['auth:api', 'verified'],
    'controller' => AuthController::class,
], function () {
    Route::get('profile', 'profile');
    Route::post('logout', 'logout');
});
