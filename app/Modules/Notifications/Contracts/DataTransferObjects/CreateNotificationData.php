<?php

namespace App\Modules\Notifications\Contracts\DataTransferObjects;

use App\Modules\Notifications\Domain\Enums\NotificationPriority;
use DateTimeImmutable;

final readonly class CreateNotificationData
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public string $recipientId,
        public string $type,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public NotificationPriority $priority = NotificationPriority::Normal,
        public ?array $data = null,
        public ?string $deduplicationKey = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {}
}
