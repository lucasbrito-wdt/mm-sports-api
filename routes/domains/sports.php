<?php

use App\Domains\Sports\Controllers\Admin\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('sports/teams', [TeamController::class, 'index']);

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('teams', [TeamController::class, 'index']);
});
