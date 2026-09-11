<?php

use App\Modules\Customer\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::post('/customers/register', [CustomerController::class, 'register'])
        ->middleware('throttle:auth.register')
        ->name('customers.register');
});
