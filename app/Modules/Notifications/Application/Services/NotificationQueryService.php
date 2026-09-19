<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Contracts\DataTransferObjects\NotificationData;
use App\Modules\Notifications\Domain\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class NotificationQueryService
{
    /**
     * Build the recipient-scoped inbox listing.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, NotificationData>
     */
    public function list(string $recipientId, array $filters): LengthAwarePaginator
    {
        $query = Notification::query()->forRecipient($recipientId);

        if (! $this->asBool($filters['include_expired'] ?? false)) {
            $query->notExpired();
        }

        if ($this->asBool($filters['unread'] ?? false)) {
            $query->whereNull('read_at');
        }

        if (filled($filters['type'] ?? null)) {
            $query->where('type', $filters['type']);
        }

        if (filled($filters['priority'] ?? null)) {
            $query->where('priority', $filters['priority']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Notification $notification): NotificationData => NotificationData::fromModel($notification));
    }

    private function asBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
