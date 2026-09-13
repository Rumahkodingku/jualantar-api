<?php

use App\Modules\Merchant\Http\Controllers\MerchantController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::middleware(['auth:sanctum', 'permission:merchant.view,sanctum'])->group(function () {
        Route::get('/merchants', [MerchantController::class, 'index'])->name('merchants.index');
        Route::get('/merchants/{merchant}', [MerchantController::class, 'show'])->name('merchants.show');
    });
});
