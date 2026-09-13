<?php

use App\Modules\Service\Http\Controllers\ServiceCategoryController;
use App\Modules\Service\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/services/{service}/categories', [ServiceCategoryController::class, 'index'])->name('services.categories.index');

    Route::middleware(['auth:sanctum', 'permission:services.manage,sanctum'])->group(function () {
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::match(['put', 'patch'], '/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });

    Route::middleware(['auth:sanctum', 'permission:categories.manage,sanctum'])->group(function () {
        Route::get('/categories', [ServiceCategoryController::class, 'adminIndex'])->name('categories.index');
        Route::post('/services/{service}/categories', [ServiceCategoryController::class, 'store'])->name('services.categories.store');
        Route::get('/categories/{category}', [ServiceCategoryController::class, 'show'])->name('categories.show');
        Route::match(['put', 'patch'], '/categories/{category}', [ServiceCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [ServiceCategoryController::class, 'destroy'])->name('categories.destroy');
    });
});
