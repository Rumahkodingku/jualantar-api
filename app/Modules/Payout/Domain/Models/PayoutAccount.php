<?php

namespace App\Modules\Payout\Domain\Models;

use App\Modules\Payout\Database\Factories\PayoutAccountFactory;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table('payout.payout_accounts')]
#[UseFactory(PayoutAccountFactory::class)]
#[Fillable([
    'owner_type',
    'owner_id',
    'bank_id',
    'account_number',
    'account_name',
    'is_primary',
    'status',
    'rejection_reason',
    'verified_at',
    'verified_by',
])]
class PayoutAccount extends Model
{
    /** @use HasFactory<PayoutAccountFactory> */
    use HasFactory, HasUuids;

    /**
     * Constrain the query to a single polymorphic owner.
     *
     * @param  Builder<PayoutAccount>  $query
     */
    public function scopeForOwner(Builder $query, PayoutOwnerType $ownerType, string $ownerId): void
    {
        $query->where('owner_type', $ownerType->value)->where('owner_id', $ownerId);
    }

    /**
     * Constrain the query to primary payout accounts.
     *
     * @param  Builder<PayoutAccount>  $query
     */
    public function scopePrimary(Builder $query): void
    {
        $query->where('is_primary', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_type' => PayoutOwnerType::class,
            'status' => PayoutStatus::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
