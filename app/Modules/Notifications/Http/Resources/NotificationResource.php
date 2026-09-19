<?php

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Contracts\DataTransferObjects\NotificationData;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationData
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'action_url' => $this->resource->actionUrl,
            'priority' => $this->resource->priority,
            'data' => $this->resource->data,
            'read_at' => $this->resource->readAt?->format(DateTimeInterface::ATOM),
            'expires_at' => $this->resource->expiresAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
