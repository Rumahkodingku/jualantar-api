<?php

namespace App\Modules\Notifications\Contracts\DataTransferObjects;

use App\Modules\Notifications\Domain\Models\Notification;
use DateTimeImmutable;

final readonly class NotificationData
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $body,
        public ?string $actionUrl,
        public string $priority,
        public ?array $data,
        public ?DateTimeImmutable $readAt,
        public ?DateTimeImmutable $expiresAt,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromModel(Notification $notification): self
    {
        return new self(
            id: $notification->id,
            type: $notification->type,
            title: $notification->title,
            body: $notification->body,
            actionUrl: $notification->action_url,
            priority: $notification->priority->value,
            data: $notification->data,
            readAt: $notification->read_at?->toDateTimeImmutable(),
            expiresAt: $notification->expires_at?->toDateTimeImmutable(),
            createdAt: $notification->created_at->toDateTimeImmutable(),
        );
    }
}
