<?php

use App\Modules\Merchant\Http\Account\MerchantAccountController;
use App\Modules\Merchant\Http\Approval\MerchantApprovalController;
use App\Modules\Merchant\Http\Catalog\MerchantController;
use App\Modules\Merchant\Http\Operations\MerchantOperationsController;
use App\Modules\Merchant\Http\Operations\OperatingHoursController;
use App\Modules\Merchant\Http\Operations\OutletOperationsController;
use App\Modules\Merchant\Http\Operations\OutletUsersController;
use App\Modules\Merchant\Http\Operations\ServiceAreaController;
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

    Route::middleware(['auth:sanctum'])->prefix('merchant/operations')->name('merchant.operations.')->group(function () {
        Route::get('/', [MerchantOperationsController::class, 'summary'])
            ->middleware('permission:merchant.operations.view,sanctum')->name('summary');
        Route::post('/activate', [MerchantOperationsController::class, 'activate'])
            ->middleware('permission:merchant.operations.status.update,sanctum')->name('activate');
        Route::post('/suspend', [MerchantOperationsController::class, 'suspend'])
            ->middleware('permission:merchant.operations.status.update,sanctum')->name('suspend');
        Route::post('/reactivate', [MerchantOperationsController::class, 'reactivate'])
            ->middleware('permission:merchant.operations.status.update,sanctum')->name('reactivate');

        Route::get('/profile', [MerchantOperationsController::class, 'showProfile'])
            ->middleware('permission:merchant.operations.view,sanctum')->name('profile.show');
        Route::patch('/profile', [MerchantOperationsController::class, 'updateProfile'])
            ->middleware('permission:merchant.operations.profile.update,sanctum')->name('profile.update');

        Route::get('/outlets', [OutletOperationsController::class, 'index'])
            ->middleware('permission:merchant.operations.outlets.view,sanctum')->name('outlets.index');
        Route::post('/outlets', [OutletOperationsController::class, 'store'])
            ->middleware('permission:merchant.operations.outlets.create,sanctum')->name('outlets.store');
        Route::get('/outlets/{outlet}', [OutletOperationsController::class, 'show'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.outlets.view,sanctum')->name('outlets.show');
        Route::patch('/outlets/{outlet}', [OutletOperationsController::class, 'update'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.outlets.update,sanctum')->name('outlets.update');
        Route::post('/outlets/{outlet}/activate', [OutletOperationsController::class, 'activate'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.outlets.status.update,sanctum')->name('outlets.activate');
        Route::post('/outlets/{outlet}/deactivate', [OutletOperationsController::class, 'deactivate'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.outlets.status.update,sanctum')->name('outlets.deactivate');

        Route::get('/outlets/{outlet}/users', [OutletUsersController::class, 'index'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.outlet_users.view,sanctum')->name('outlets.users.index');
        Route::post('/outlets/{outlet}/users', [OutletUsersController::class, 'store'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.outlet_users.assign,sanctum')->name('outlets.users.store');
        Route::patch('/outlets/{outlet}/users/{user}', [OutletUsersController::class, 'update'])
            ->whereUuid('outlet')->whereUuid('user')
            ->middleware('permission:merchant.operations.outlet_users.role.update,sanctum')->name('outlets.users.update');
        Route::delete('/outlets/{outlet}/users/{user}', [OutletUsersController::class, 'destroy'])
            ->whereUuid('outlet')->whereUuid('user')
            ->middleware('permission:merchant.operations.outlet_users.remove,sanctum')->name('outlets.users.destroy');

        Route::get('/outlets/{outlet}/operating-hours', [OperatingHoursController::class, 'show'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.hours.view,sanctum')->name('outlets.operating_hours.show');
        Route::put('/outlets/{outlet}/operating-hours', [OperatingHoursController::class, 'update'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.hours.update,sanctum')->name('outlets.operating_hours.update');

        Route::get('/outlets/{outlet}/service-area', [ServiceAreaController::class, 'show'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.service_area.view,sanctum')->name('outlets.service_area.show');
        Route::put('/outlets/{outlet}/service-area', [ServiceAreaController::class, 'update'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.service_area.update,sanctum')->name('outlets.service_area.update');

        Route::get('/outlets/{outlet}/availability', [OutletOperationsController::class, 'availability'])
            ->whereUuid('outlet')
            ->middleware('permission:merchant.operations.availability.view,sanctum')->name('outlets.availability');
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
