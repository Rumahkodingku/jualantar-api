<?php

use App\Modules\Customer\Http\Controllers\AuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::post('/auth/register/customer', [AuthenticationController::class, 'register'])
        ->middleware('throttle:auth.register')
        ->name('auth.register.customer');

    Route::post('/auth/login', [AuthenticationController::class, 'login'])
        ->middleware('throttle:auth.login')
        ->name('auth.login');

    Route::post('/auth/email/verification-notification', [AuthenticationController::class, 'resendVerification'])
        ->middleware('throttle:auth.verification.resend')
        ->name('auth.email.verification.notification');
});

Route::middleware(['api', 'signed'])->prefix('api/v1')->group(function () {
    Route::get('/auth/email/verify/{id}/{hash}', [AuthenticationController::class, 'verifyEmail'])
        ->name('verification.verify');
});
