<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantApprovalRevisionItemFactory;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('merchant.merchant_approval_revision_items')]
#[UseFactory(MerchantApprovalRevisionItemFactory::class)]
#[Fillable([
    'revision_id',
    'component',
    'subject_type',
    'subject_id',
    'reason',
    'resolved_at',
])]
class MerchantApprovalRevisionItem extends Model
{
    /** @use HasFactory<MerchantApprovalRevisionItemFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<MerchantApprovalRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(MerchantApprovalRevision::class, 'revision_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'component' => MerchantApprovalComponent::class,
            'subject_type' => MerchantApprovalSubjectType::class,
            'resolved_at' => 'datetime',
        ];
    }
}
