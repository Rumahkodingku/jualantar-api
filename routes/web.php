<?php

use App\Shared\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/metrics', MetricsController::class);
