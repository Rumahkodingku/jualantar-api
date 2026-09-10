<?php

use App\Modules\IdentityAccess\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::get('/user', [UserController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('user');
});
