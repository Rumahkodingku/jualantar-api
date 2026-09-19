<?php

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\Contracts\DataTransferObjects\CreateNotificationData;
use App\Modules\Notifications\Contracts\DataTransferObjects\NotificationData;

/**
 * Public seam for creating and managing recipient-scoped in-app notifications.
 *
 * Future business modules depend on this contract only and must never import
 * the Notifications domain models. Implementations translate expected failures
 * into the application's ApiException hierarchy.
 */
interface Notifications
{
    /**
     * Persist a notification, honouring an optional deduplication key.
     *
     * When the recipient/key pair already exists, the existing notification is
     * returned instead of creating a duplicate.
     */
    public function create(CreateNotificationData $data): NotificationData;

    /**
     * Mark one recipient-owned notification as read.
     *
     * Repeated calls are idempotent and preserve the first-read timestamp.
     */
    public function markAsRead(string $notificationId, string $recipientId): NotificationData;

    /**
     * Mark every active unread notification for the recipient as read.
     *
     * @return int the number of notifications affected
     */
    public function markAllAsRead(string $recipientId): int;

    /**
     * Count active unread notifications for the recipient.
     */
    public function unreadCount(string $recipientId): int;

    /**
     * Delete one recipient-owned notification.
     */
    public function delete(string $notificationId, string $recipientId): void;
}
