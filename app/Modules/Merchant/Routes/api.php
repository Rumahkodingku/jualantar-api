<?php

use App\Modules\Merchant\Http\Account\MerchantAccountController;
use App\Modules\Merchant\Http\Approval\MerchantApprovalController;
use App\Modules\Merchant\Http\Catalog\MerchantController;
use App\Modules\Merchant\Http\Registration\MerchantRegistrationController;
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
        Route::delete('/documents/{document}', [MerchantRegistrationController::class, 'destroyDocument'])
            ->whereUuid('document')
            ->name('documents.destroy');

        Route::put('/payout-account', [MerchantRegistrationController::class, 'savePayoutAccount'])->name('payout_account.update');

        Route::get('/review', [MerchantRegistrationController::class, 'review'])->name('review');
        Route::post('/submit', [MerchantRegistrationController::class, 'submit'])->name('submit');
    });

    Route::middleware(['auth:sanctum', 'permission:merchant.view,sanctum'])->group(function () {
        Route::get('/merchants', [MerchantController::class, 'index'])->name('merchants.index');
        Route::get('/merchants/{merchant}', [MerchantController::class, 'show'])->name('merchants.show')->whereUuid('merchant');
    });

    Route::middleware(['auth:sanctum'])->prefix('admin/merchant-approvals')->name('admin.merchant_approvals.')->group(function () {
        Route::get('/summary', [MerchantApprovalController::class, 'summary'])
            ->middleware('permission:merchant.approval.view,sanctum')->name('summary');
        Route::get('/', [MerchantApprovalController::class, 'index'])
            ->middleware('permission:merchant.approval.view,sanctum')->name('index');
        Route::get('/{approval}', [MerchantApprovalController::class, 'show'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.view,sanctum')->name('show');
        Route::post('/{approval}/claim', [MerchantApprovalController::class, 'claim'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.claim,sanctum')->name('claim');
        Route::post('/{approval}/release', [MerchantApprovalController::class, 'release'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.claim,sanctum')->name('release');
        Route::post('/{approval}/reviews', [MerchantApprovalController::class, 'review'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.review,sanctum')->name('reviews.store');
        Route::post('/{approval}/revision', [MerchantApprovalController::class, 'revision'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.revision,sanctum')->name('revision.store');
        Route::post('/{approval}/reject', [MerchantApprovalController::class, 'reject'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.reject,sanctum')->name('reject');
        Route::post('/{approval}/approve', [MerchantApprovalController::class, 'approve'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.approve,sanctum')->name('approve');
        Route::get('/{approval}/events', [MerchantApprovalController::class, 'events'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.view,sanctum')->name('events');
        Route::get('/{approval}/revisions', [MerchantApprovalController::class, 'revisions'])
            ->whereUuid('approval')
            ->middleware('permission:merchant.approval.view,sanctum')->name('revisions');
    });
});
