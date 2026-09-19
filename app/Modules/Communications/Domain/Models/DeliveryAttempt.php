<?php

namespace App\Modules\Communications\Domain\Models;

use App\Modules\Communications\Database\Factories\DeliveryAttemptFactory;
use App\Modules\Communications\Domain\Enums\DeliveryAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('communications.communication_delivery_attempts')]
#[UseFactory(DeliveryAttemptFactory::class)]
#[Fillable([
    'communication_id',
    'attempt_number',
    'status',
    'provider',
    'provider_message_id',
    'error_code',
    'error_message',
    'started_at',
    'finished_at',
])]
class DeliveryAttempt extends Model
{
    /** @use HasFactory<DeliveryAttemptFactory> */
    use HasFactory, HasUuids;

    /**
     * Delivery attempts are append-only; only created_at is tracked.
     */
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Communication, DeliveryAttempt>
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryAttemptStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
