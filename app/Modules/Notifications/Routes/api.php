<?php

use App\Modules\Notifications\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->name('api.v1.')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');

        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])
            ->name('notifications.unread_count');

        Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])
            ->name('notifications.read_all');

        Route::get('/notifications/{notification}', [NotificationController::class, 'show'])
            ->name('notifications.show');

        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])
            ->name('notifications.read');

        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])
            ->name('notifications.destroy');
    });
});
