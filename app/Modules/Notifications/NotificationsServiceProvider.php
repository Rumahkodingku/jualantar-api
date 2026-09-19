<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Application\Services\EloquentNotifications;
use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Notifications\Domain\Exceptions\NotificationNotFoundException;
use App\Modules\Notifications\Domain\Models\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Notifications::class, EloquentNotifications::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        $this->bindNotificationRoute();
    }

    /**
     * Resolve {notification} within the authenticated recipient's inbox only.
     *
     * A missing, expired, or foreign notification all resolve to the same
     * not-found error so notification ownership cannot be enumerated.
     */
    private function bindNotificationRoute(): void
    {
        Route::bind('notification', function (string $value): Notification {
            $recipientId = (string) request()->user()?->id;

            return Notification::query()
                ->forRecipient($recipientId)
                ->notExpired()
                ->whereKey($value)
                ->first() ?? throw new NotificationNotFoundException;
        });
    }
}
