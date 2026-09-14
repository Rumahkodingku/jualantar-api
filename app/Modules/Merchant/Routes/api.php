<?php

use App\Modules\Merchant\Http\Controllers\MerchantAccountController;
use App\Modules\Merchant\Http\Controllers\MerchantController;
use App\Modules\Merchant\Http\Controllers\MerchantRegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::post('/merchants/register', [MerchantAccountController::class, 'register'])
        ->middleware('throttle:merchant.register')
        ->name('merchants.register');

    Route::middleware(['auth:sanctum'])->prefix('merchants/registration')->name('merchants.registration.')->group(function () {
        Route::post('/', [MerchantRegistrationController::class, 'store'])->name('store');
        Route::get('/', [MerchantRegistrationController::class, 'show'])->name('show');
        Route::patch('/', [MerchantRegistrationController::class, 'update'])->name('update');

        Route::put('/identity', [MerchantRegistrationController::class, 'saveIdentity'])->name('identity.update');
        Route::put('/legal-entity', [MerchantRegistrationController::class, 'saveLegalEntity'])->name('legal_entity.update');
        Route::put('/service', [MerchantRegistrationController::class, 'saveService'])->name('service.update');
        Route::put('/categories', [MerchantRegistrationController::class, 'saveCategories'])->name('categories.update');

        Route::post('/outlets', [MerchantRegistrationController::class, 'storeOutlet'])->name('outlets.store');
        Route::patch('/outlets/{outlet}', [MerchantRegistrationController::class, 'updateOutlet'])->name('outlets.update');
        Route::delete('/outlets/{outlet}', [MerchantRegistrationController::class, 'destroyOutlet'])->name('outlets.destroy');

        Route::post('/uploads', [MerchantRegistrationController::class, 'storeUpload'])->name('uploads.store');
        Route::post('/documents', [MerchantRegistrationController::class, 'storeDocument'])->name('documents.store');

        Route::put('/payout-account', [MerchantRegistrationController::class, 'savePayoutAccount'])->name('payout_account.update');

        Route::get('/review', [MerchantRegistrationController::class, 'review'])->name('review');
        Route::post('/submit', [MerchantRegistrationController::class, 'submit'])->name('submit');
        Route::post('/reopen', [MerchantRegistrationController::class, 'reopen'])->name('reopen');
    });

    Route::middleware(['auth:sanctum', 'permission:merchant.view,sanctum'])->group(function () {
        Route::get('/merchants', [MerchantController::class, 'index'])->name('merchants.index');
        Route::get('/merchants/{merchant}', [MerchantController::class, 'show'])->name('merchants.show')->whereUuid('merchant');
    });
});
