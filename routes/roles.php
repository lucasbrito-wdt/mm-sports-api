<?php

use App\Domains\ACL\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'verified'])->group(function () {
    /*
     * Roles
    */
    Route::apiResource('roles', RoleController::class);
    Route::group(['prefix' => 'roles', 'as' => 'roles.'], function () {
        Route::get('list/permissions', [RoleController::class, 'listPermissions'])->name('list.permissions');
        Route::get('atribuir-role/{user:id}/{role:id}', [RoleController::class, 'atribuirUserRole'])->name('atribuir-user-role');
        Route::get(
            'atribuir-role-permission/{user:id}/{role:id}',
            [RoleController::class, 'atribuirUserRolePermission'],
        )->name('atribuir-user-role-permission');
        Route::get('remover-role/{user:id}/{role:id}', [RoleController::class, 'removeUserRolePermission'])->name('remover-user-role');
    });
});
