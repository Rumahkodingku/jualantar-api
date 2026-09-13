<?php

use App\Modules\Payout\Http\Controllers\PayoutAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::middleware(['auth:sanctum', 'permission:payout_accounts.manage,sanctum'])->group(function () {
        Route::get('/payout-accounts', [PayoutAccountController::class, 'index'])->name('payout_accounts.index');
        Route::get('/payout-accounts/{payoutAccount}', [PayoutAccountController::class, 'show'])->name('payout_accounts.show');
    });
});
