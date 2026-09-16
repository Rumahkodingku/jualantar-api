<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantApprovalFactory;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('merchant.merchant_approvals')]
#[UseFactory(MerchantApprovalFactory::class)]
#[Fillable([
    'application_id',
    'assigned_to',
    'assigned_at',
    'started_at',
    'completed_at',
    'decision',
    'decision_reason',
])]
class MerchantApproval extends Model
{
    /** @use HasFactory<MerchantApprovalFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<MerchantApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(MerchantApplication::class, 'application_id');
    }

    /**
     * @return HasMany<MerchantApprovalReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(MerchantApprovalReview::class, 'approval_id');
    }

    /**
     * @return HasMany<MerchantApprovalRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(MerchantApprovalRevision::class, 'approval_id');
    }

    /**
     * @return HasMany<MerchantApprovalEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(MerchantApprovalEvent::class, 'approval_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => MerchantApprovalDecision::class,
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
