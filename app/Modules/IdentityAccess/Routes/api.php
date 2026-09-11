<?php

use App\Modules\IdentityAccess\Http\Controllers\PermissionController;
use App\Modules\IdentityAccess\Http\Controllers\RoleController;
use App\Modules\IdentityAccess\Http\Controllers\UserController;
use App\Modules\IdentityAccess\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::get('/user', [UserController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('user');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:roles.view,sanctum')->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:roles.create,sanctum')->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])
            ->middleware('permission:roles.view,sanctum')->name('roles.show');
        Route::match(['put', 'patch'], '/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles.update,sanctum')->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles.delete,sanctum')->name('roles.destroy');

        Route::post('/roles/{role}/permissions', [RoleController::class, 'assignPermissions'])
            ->middleware('permission:roles.update,sanctum')->name('roles.permissions.assign');
        Route::delete('/roles/{role}/permissions', [RoleController::class, 'removePermissions'])
            ->middleware('permission:roles.update,sanctum')->name('roles.permissions.remove');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:permissions.view,sanctum')->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])
            ->middleware('permission:permissions.create,sanctum')->name('permissions.store');
        Route::get('/permissions/{permission}', [PermissionController::class, 'show'])
            ->middleware('permission:permissions.view,sanctum')->name('permissions.show');
        Route::match(['put', 'patch'], '/permissions/{permission}', [PermissionController::class, 'update'])
            ->middleware('permission:permissions.update,sanctum')->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
            ->middleware('permission:permissions.delete,sanctum')->name('permissions.destroy');

        Route::get('/users/{user}/roles', [UserRoleController::class, 'index'])
            ->middleware('permission:users.view,sanctum')->name('users.roles.index');
        Route::post('/users/{user}/roles', [UserRoleController::class, 'store'])
            ->middleware('permission:users.update,sanctum')->name('users.roles.store');
        Route::delete('/users/{user}/roles', [UserRoleController::class, 'destroy'])
            ->middleware('permission:users.update,sanctum')->name('users.roles.destroy');
    });
});
