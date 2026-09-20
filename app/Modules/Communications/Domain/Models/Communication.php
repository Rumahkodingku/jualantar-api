<?php

namespace App\Modules\Communications\Domain\Models;

use App\Modules\Communications\Contracts\Enums\CommunicationChannel;
use App\Modules\Communications\Contracts\Enums\CommunicationStatus;
use App\Modules\Communications\Database\Factories\CommunicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('communications.communications')]
#[UseFactory(CommunicationFactory::class)]
#[Fillable([
    'channel',
    'type',
    'recipient_address',
    'subject',
    'template',
    'payload',
    'idempotency_key',
    'status',
    'queued_at',
    'sent_at',
    'delivered_at',
    'failed_at',
    'last_error_code',
    'last_error_message',
    'metadata',
])]
class Communication extends Model
{
    /** @use HasFactory<CommunicationFactory> */
    use HasFactory, HasUuids;

    /**
     * @return HasMany<DeliveryAttempt>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'status' => CommunicationStatus::class,
            'payload' => 'encrypted:array',
            'metadata' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
