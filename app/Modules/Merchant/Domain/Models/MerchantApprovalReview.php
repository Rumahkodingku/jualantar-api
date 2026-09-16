<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantApprovalReviewFactory;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalReviewStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current review state per subject. Audit history lives in
 * MerchantApprovalEvent.
 */
#[Table('merchant.merchant_approval_reviews')]
#[UseFactory(MerchantApprovalReviewFactory::class)]
#[Fillable([
    'approval_id',
    'component',
    'subject_type',
    'subject_id',
    'status',
    'note',
    'verified_by',
    'verified_at',
    'snapshot_id',
    'content_hash',
])]
class MerchantApprovalReview extends Model
{
    /** @use HasFactory<MerchantApprovalReviewFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<MerchantApproval, $this>
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(MerchantApproval::class, 'approval_id');
    }

    /**
     * @return BelongsTo<MerchantApplicationSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(MerchantApplicationSnapshot::class, 'snapshot_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'component' => MerchantApprovalComponent::class,
            'subject_type' => MerchantApprovalSubjectType::class,
            'status' => MerchantApprovalReviewStatus::class,
            'verified_at' => 'datetime',
        ];
    }
}
