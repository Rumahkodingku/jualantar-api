<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantApprovalRevisionFactory;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalRevisionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('merchant.merchant_approval_revisions')]
#[UseFactory(MerchantApprovalRevisionFactory::class)]
#[Fillable([
    'approval_id',
    'requested_by',
    'note',
    'status',
    'requested_at',
    'resolved_at',
])]
class MerchantApprovalRevision extends Model
{
    /** @use HasFactory<MerchantApprovalRevisionFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<MerchantApproval, $this>
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(MerchantApproval::class, 'approval_id');
    }

    /**
     * @return HasMany<MerchantApprovalRevisionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MerchantApprovalRevisionItem::class, 'revision_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MerchantApprovalRevisionStatus::class,
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
