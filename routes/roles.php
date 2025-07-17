<?php

use Illuminate\Support\Facades\Route;
use App\Domains\ACL\Controllers\RoleController;

Route::middleware(['auth:api', 'verified'])->group(function () {
    /*
     * Roles
    */
    Route::apiResource('roles', RoleController::class);
    Route::group(['prefix' => 'roles', 'as' => 'roles'], function () {
        Route::get('list/permissions', [RoleController::class, 'listPermissions']);
        Route::get('atribuir-role/{user:id}/{role:id}', [RoleController::class, 'atribuirUserRole']);
        Route::get(
            'atribuir-role-permission/{user:id}/{role:id}',
            [RoleController::class, 'atribuirUserRolePermission'],
        );
        Route::get('remover-role/{user:id}/{role:id}', [RoleController::class, 'removeUserRolePermission']);
    });
});
