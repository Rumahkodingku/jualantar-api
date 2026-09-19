<?php

namespace App\Modules\Notifications\Domain\Models;

use App\Modules\Notifications\Database\Factories\NotificationFactory;
use App\Modules\Notifications\Domain\Enums\NotificationPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table('notifications.notifications')]
#[UseFactory(NotificationFactory::class)]
#[Fillable([
    'recipient_id',
    'type',
    'title',
    'body',
    'action_url',
    'priority',
    'data',
    'deduplication_key',
    'read_at',
    'expires_at',
])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, HasUuids;

    /**
     * Constrain the query to a single recipient inbox.
     *
     * @param  Builder<Notification>  $query
     */
    public function scopeForRecipient(Builder $query, string $recipientId): void
    {
        $query->where('recipient_id', $recipientId);
    }

    /**
     * Exclude notifications whose expiry has passed.
     *
     * @param  Builder<Notification>  $query
     */
    public function scopeNotExpired(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => NotificationPriority::class,
            'data' => 'array',
            'read_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
