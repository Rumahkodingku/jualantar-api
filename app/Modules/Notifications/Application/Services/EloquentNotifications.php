<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Actions\CreateNotification;
use App\Modules\Notifications\Application\Actions\DeleteNotification;
use App\Modules\Notifications\Application\Actions\GetUnreadNotificationCount;
use App\Modules\Notifications\Application\Actions\MarkAllNotificationsAsRead;
use App\Modules\Notifications\Application\Actions\MarkNotificationAsRead;
use App\Modules\Notifications\Contracts\DataTransferObjects\CreateNotificationData;
use App\Modules\Notifications\Contracts\DataTransferObjects\NotificationData;
use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Notifications\Domain\Exceptions\NotificationNotFoundException;
use App\Modules\Notifications\Domain\Models\Notification;
use App\Shared\Exceptions\UnprocessableEntityException;
use App\Shared\Result\Result;

/**
 * Contract implementation for trusted application code.
 *
 * Delegates to the module's actions so the domain rules live in one place,
 * and translates Result failures into the ApiException hierarchy expected by
 * callers of the public contract.
 */
final class EloquentNotifications implements Notifications
{
    public function __construct(
        private readonly CreateNotification $createNotification,
        private readonly MarkNotificationAsRead $markNotificationAsRead,
        private readonly MarkAllNotificationsAsRead $markAllNotificationsAsRead,
        private readonly GetUnreadNotificationCount $getUnreadNotificationCount,
        private readonly DeleteNotification $deleteNotification,
    ) {}

    public function create(CreateNotificationData $data): NotificationData
    {
        return $this->unwrap(($this->createNotification)($data));
    }

    public function markAsRead(string $notificationId, string $recipientId): NotificationData
    {
        return $this->unwrap(
            ($this->markNotificationAsRead)($this->findScoped($notificationId, $recipientId)),
        );
    }

    public function markAllAsRead(string $recipientId): int
    {
        return $this->unwrap(($this->markAllNotificationsAsRead)($recipientId));
    }

    public function unreadCount(string $recipientId): int
    {
        return $this->unwrap(($this->getUnreadNotificationCount)($recipientId));
    }

    public function delete(string $notificationId, string $recipientId): void
    {
        $this->unwrap(
            ($this->deleteNotification)($this->findScoped($notificationId, $recipientId)),
        );
    }

    private function findScoped(string $notificationId, string $recipientId): Notification
    {
        return Notification::query()
            ->forRecipient($recipientId)
            ->notExpired()
            ->whereKey($notificationId)
            ->first() ?? throw new NotificationNotFoundException;
    }

    private function unwrap(Result $result): mixed
    {
        if ($result->isErr()) {
            $error = $result->error();

            if ($error->code === 'notification_not_found') {
                throw new NotificationNotFoundException($error->message);
            }

            throw new UnprocessableEntityException($error->message, $error->code, $error->fields);
        }

        return $result->unwrap();
    }
}
