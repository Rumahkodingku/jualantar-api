<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\MerchantApprovalRevisionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MerchantApprovalRevisionItem
 */
class MerchantApprovalRevisionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'revision_id' => $this->revision_id,
            'component' => $this->component->value,
            'subject_type' => $this->subject_type->value,
            'subject_id' => $this->subject_id,
            'reason' => $this->reason,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
