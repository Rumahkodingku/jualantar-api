<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantApprovalEventFactory;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log for an approval. Events are never updated or deleted.
 */
#[Table('merchant.merchant_approval_events')]
#[UseFactory(MerchantApprovalEventFactory::class)]
#[Fillable(['approval_id', 'event_type', 'actor_id', 'metadata'])]
class MerchantApprovalEvent extends Model
{
    /** @use HasFactory<MerchantApprovalEventFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    /**
     * @return BelongsTo<MerchantApproval, $this>
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(MerchantApproval::class, 'approval_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => MerchantApprovalEventType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
