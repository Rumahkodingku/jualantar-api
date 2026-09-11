<?php

use App\Modules\Geography\Http\Controllers\DistrictController;
use App\Modules\Geography\Http\Controllers\ProvinceController;
use App\Modules\Geography\Http\Controllers\RegencyController;
use App\Modules\Geography\Http\Controllers\VillageController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::get('/provinces', [ProvinceController::class, 'index'])->name('provinces.index');
    Route::get('/provinces/{province}', [ProvinceController::class, 'show'])->name('provinces.show');

    Route::get('/regencies', [RegencyController::class, 'index'])->name('regencies.index');
    Route::get('/regencies/{regency}', [RegencyController::class, 'show'])->name('regencies.show');

    Route::get('/districts', [DistrictController::class, 'index'])->name('districts.index');
    Route::get('/districts/{district}', [DistrictController::class, 'show'])->name('districts.show');

    Route::get('/villages', [VillageController::class, 'index'])->name('villages.index');
    Route::get('/villages/{village}', [VillageController::class, 'show'])->name('villages.show');

    Route::middleware(['auth:sanctum', 'permission:geography.update,sanctum'])->group(function () {
        Route::patch('/provinces/{province}', [ProvinceController::class, 'update'])->name('provinces.update');
        Route::delete('/provinces/{province}', [ProvinceController::class, 'destroy'])->name('provinces.destroy');

        Route::patch('/regencies/{regency}', [RegencyController::class, 'update'])->name('regencies.update');
        Route::delete('/regencies/{regency}', [RegencyController::class, 'destroy'])->name('regencies.destroy');

        Route::patch('/districts/{district}', [DistrictController::class, 'update'])->name('districts.update');
        Route::delete('/districts/{district}', [DistrictController::class, 'destroy'])->name('districts.destroy');

        Route::patch('/villages/{village}', [VillageController::class, 'update'])->name('villages.update');
        Route::delete('/villages/{village}', [VillageController::class, 'destroy'])->name('villages.destroy');
    });
});
