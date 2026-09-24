<?php

use App\Modules\Merchant\Http\Account\MerchantAccountController;
use App\Modules\Merchant\Http\Approval\MerchantApprovalController;
use App\Modules\Merchant\Http\Catalog\CategoryController;
use App\Modules\Merchant\Http\Catalog\OutletCatalogController;
use App\Modules\Merchant\Http\Catalog\ProductController;
use App\Modules\Merchant\Http\Catalog\ProductMediaController;
use App\Modules\Merchant\Http\Catalog\ProductOutletController;
use App\Modules\Merchant\Http\Catalog\ProductVariantController;
use App\Modules\Merchant\Http\Merchants\MerchantController;
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

    Route::middleware(['auth:sanctum', 'merchant.context'])->prefix('merchant/operations')->name('merchant.operations.')->group(function () {
        Route::get('/', [MerchantOperationsController::class, 'summary'])->name('summary');
        Route::post('/activate', [MerchantOperationsController::class, 'activate'])
            ->middleware('merchant.owner')->name('activate');
        Route::post('/suspend', [MerchantOperationsController::class, 'suspend'])
            ->middleware('merchant.owner')->name('suspend');
        Route::post('/reactivate', [MerchantOperationsController::class, 'reactivate'])
            ->middleware('merchant.owner')->name('reactivate');

        Route::get('/profile', [MerchantOperationsController::class, 'showProfile'])->name('profile.show');
        Route::patch('/profile', [MerchantOperationsController::class, 'updateProfile'])
            ->middleware('merchant.owner')->name('profile.update');

        Route::post('/uploads', [MerchantOperationsController::class, 'storeUpload'])->name('uploads.store');

        Route::get('/outlets', [OutletOperationsController::class, 'index'])->name('outlets.index');
        Route::post('/outlets', [OutletOperationsController::class, 'store'])
            ->middleware('merchant.owner')->name('outlets.store');
        Route::get('/outlets/{outlet}', [OutletOperationsController::class, 'show'])
            ->whereUuid('outlet')->name('outlets.show');
        Route::patch('/outlets/{outlet}', [OutletOperationsController::class, 'update'])
            ->whereUuid('outlet')->name('outlets.update');
        Route::post('/outlets/{outlet}/activate', [OutletOperationsController::class, 'activate'])
            ->whereUuid('outlet')->name('outlets.activate');
        Route::post('/outlets/{outlet}/deactivate', [OutletOperationsController::class, 'deactivate'])
            ->whereUuid('outlet')->name('outlets.deactivate');

        Route::get('/outlets/{outlet}/users', [OutletUsersController::class, 'index'])
            ->whereUuid('outlet')->name('outlets.users.index');
        Route::post('/outlets/{outlet}/users', [OutletUsersController::class, 'store'])
            ->whereUuid('outlet')->name('outlets.users.store');
        Route::patch('/outlets/{outlet}/users/{user}', [OutletUsersController::class, 'update'])
            ->whereUuid('outlet')->whereUuid('user')->name('outlets.users.update');
        Route::delete('/outlets/{outlet}/users/{user}', [OutletUsersController::class, 'destroy'])
            ->whereUuid('outlet')->whereUuid('user')->name('outlets.users.destroy');

        Route::post('/outlets/{outlet}/employees', [OutletUsersController::class, 'storeEmployee'])
            ->whereUuid('outlet')->name('outlets.employees.store');

        Route::get('/outlets/{outlet}/operating-hours', [OperatingHoursController::class, 'show'])
            ->whereUuid('outlet')->name('outlets.operating_hours.show');
        Route::put('/outlets/{outlet}/operating-hours', [OperatingHoursController::class, 'update'])
            ->whereUuid('outlet')->name('outlets.operating_hours.update');

        Route::get('/outlets/{outlet}/service-area', [ServiceAreaController::class, 'show'])
            ->whereUuid('outlet')->name('outlets.service_area.show');
        Route::put('/outlets/{outlet}/service-area', [ServiceAreaController::class, 'update'])
            ->whereUuid('outlet')->name('outlets.service_area.update');

        Route::get('/outlets/{outlet}/availability', [OutletOperationsController::class, 'availability'])
            ->whereUuid('outlet')->name('outlets.availability');
    });

    Route::middleware(['auth:sanctum', 'merchant.context'])
        ->prefix('merchant/catalog')
        ->name('merchant.catalog.')
        ->group(function (): void {
            Route::middleware('merchant.owner')->group(function (): void {
                Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
                Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
                Route::put('/categories/order', [CategoryController::class, 'reorder'])->name('categories.order');
                Route::get('/categories/{category}', [CategoryController::class, 'show'])
                    ->whereUuid('category')->name('categories.show');
                Route::patch('/categories/{category}', [CategoryController::class, 'update'])
                    ->whereUuid('category')->name('categories.update');
                Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
                    ->whereUuid('category')->name('categories.destroy');
                Route::post('/categories/{category}/activate', [CategoryController::class, 'activate'])
                    ->whereUuid('category')->name('categories.activate');
                Route::post('/categories/{category}/deactivate', [CategoryController::class, 'deactivate'])
                    ->whereUuid('category')->name('categories.deactivate');

                Route::get('/products', [ProductController::class, 'index'])->name('products.index');
                Route::post('/products', [ProductController::class, 'store'])->name('products.store');
                Route::put('/products/order', [ProductController::class, 'reorder'])->name('products.order');
                Route::get('/products/{product}', [ProductController::class, 'show'])
                    ->whereUuid('product')->name('products.show');
                Route::patch('/products/{product}', [ProductController::class, 'update'])
                    ->whereUuid('product')->name('products.update');
                Route::delete('/products/{product}', [ProductController::class, 'destroy'])
                    ->whereUuid('product')->name('products.destroy');
                Route::post('/products/{product}/activate', [ProductController::class, 'activate'])
                    ->whereUuid('product')->name('products.activate');
                Route::post('/products/{product}/deactivate', [ProductController::class, 'deactivate'])
                    ->whereUuid('product')->name('products.deactivate');

                Route::get('/products/{product}/variants', [ProductVariantController::class, 'index'])
                    ->whereUuid('product')->name('products.variants.index');
                Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])
                    ->whereUuid('product')->name('products.variants.store');
                Route::put('/products/{product}/variants/order', [ProductVariantController::class, 'reorder'])
                    ->whereUuid('product')->name('products.variants.order');
                Route::get('/products/{product}/variants/{variant}', [ProductVariantController::class, 'show'])
                    ->whereUuid('product')->whereUuid('variant')->name('products.variants.show');
                Route::patch('/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
                    ->whereUuid('product')->whereUuid('variant')->name('products.variants.update');
                Route::delete('/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])
                    ->whereUuid('product')->whereUuid('variant')->name('products.variants.destroy');
                Route::post('/products/{product}/variants/{variant}/activate', [ProductVariantController::class, 'activate'])
                    ->whereUuid('product')->whereUuid('variant')->name('products.variants.activate');
                Route::post('/products/{product}/variants/{variant}/deactivate', [ProductVariantController::class, 'deactivate'])
                    ->whereUuid('product')->whereUuid('variant')->name('products.variants.deactivate');

                Route::get('/products/{product}/media', [ProductMediaController::class, 'index'])
                    ->whereUuid('product')->name('products.media.index');
                Route::post('/products/{product}/media/upload-url', [ProductMediaController::class, 'storeUploadUrl'])
                    ->whereUuid('product')->name('products.media.upload_url');
                Route::post('/products/{product}/media', [ProductMediaController::class, 'store'])
                    ->whereUuid('product')->name('products.media.store');
                Route::put('/products/{product}/media/order', [ProductMediaController::class, 'reorder'])
                    ->whereUuid('product')->name('products.media.order');
                Route::get('/products/{product}/media/{media}', [ProductMediaController::class, 'show'])
                    ->whereUuid('product')->whereUuid('media')->name('products.media.show');
                Route::delete('/products/{product}/media/{media}', [ProductMediaController::class, 'destroy'])
                    ->whereUuid('product')->whereUuid('media')->name('products.media.destroy');
                Route::post('/products/{product}/media/{media}/primary', [ProductMediaController::class, 'setPrimary'])
                    ->whereUuid('product')->whereUuid('media')->name('products.media.primary');

                Route::get('/products/{product}/outlets', [ProductOutletController::class, 'index'])
                    ->whereUuid('product')->name('products.outlets.index');
                Route::post('/products/{product}/outlets', [ProductOutletController::class, 'store'])
                    ->whereUuid('product')->name('products.outlets.store');
                Route::put('/products/{product}/outlets', [ProductOutletController::class, 'replace'])
                    ->whereUuid('product')->name('products.outlets.replace');
                Route::delete('/products/{product}/outlets/{outlet}', [ProductOutletController::class, 'destroy'])
                    ->whereUuid('product')->whereUuid('outlet')->name('products.outlets.destroy');
            });

            Route::post('/products/{product}/outlets/{outlet}/activate', [ProductOutletController::class, 'activate'])
                ->whereUuid('product')->whereUuid('outlet')->name('products.outlets.activate');
            Route::post('/products/{product}/outlets/{outlet}/deactivate', [ProductOutletController::class, 'deactivate'])
                ->whereUuid('product')->whereUuid('outlet')->name('products.outlets.deactivate');
            Route::post('/products/{product}/outlets/{outlet}/availability', [ProductOutletController::class, 'availability'])
                ->whereUuid('product')->whereUuid('outlet')->name('products.outlets.availability');

            Route::get('/outlets/{outlet}/products', [OutletCatalogController::class, 'index'])
                ->whereUuid('outlet')->name('outlets.products.index');
            Route::put('/outlets/{outlet}/products/order', [OutletCatalogController::class, 'reorder'])
                ->whereUuid('outlet')->name('outlets.products.order');
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
