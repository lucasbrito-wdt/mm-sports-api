
<?php

use App\Domains\ACL\Controllers\PermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'verified'])->group(function () {
    /*
     * Permissions
     */
    Route::apiResource('permission', PermissionController::class);
    Route::group(['prefix' => 'permission', 'as' => 'permission.'], function () {
        Route::post('create/all', [PermissionController::class, 'storeAll'])->name('create.all');
        Route::put('update/all', [PermissionController::class, 'updateAll'])->name('update.all');
        Route::delete('destroy/all/{name}', [PermissionController::class, 'destroyAll'])->name('destroy.all');
        Route::get('list/actions', [PermissionController::class, 'listActions'])->name('list.actions');
    });
});
