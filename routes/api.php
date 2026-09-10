<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Each module registers its own versioned API routes from its service
| provider (see app/Modules/{Module}/Routes/api.php). This file stays as the
| application-level index for any future cross-cutting API routes.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    //
});
