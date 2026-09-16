<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property array{
 *     approval: MerchantApproval,
 *     merchant: Merchant,
 *     snapshot: MerchantApplicationSnapshot|null,
 *     reviews: Collection<int, mixed>,
 *     revisions: Collection<int, mixed>,
 *     events: Collection<int, mixed>
 * } $resource
 */
class MerchantApprovalDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $approval = $this->resource['approval'];
        $merchant = $this->resource['merchant'];
        $snapshot = $this->resource['snapshot'];

        return [
            'id' => $approval->id,
            'application' => MerchantApplicationResource::make($approval->application),
            'merchant' => MerchantResource::make($merchant),
            'assigned_to' => $approval->assigned_to,
            'assigned_at' => $approval->assigned_at?->toIso8601String(),
            'started_at' => $approval->started_at?->toIso8601String(),
            'completed_at' => $approval->completed_at?->toIso8601String(),
            'decision' => $approval->decision?->value,
            'decision_reason' => $approval->decision_reason,
            'current_snapshot' => $snapshot === null ? null : [
                'id' => $snapshot->id,
                'version' => $snapshot->version,
                'submitted_at' => $snapshot->submitted_at?->toIso8601String(),
                'data' => $snapshot->snapshot,
            ],
            'reviews' => MerchantApprovalReviewResource::collection($this->resource['reviews']),
            'revisions' => MerchantApprovalRevisionResource::collection($this->resource['revisions']),
            'events' => MerchantApprovalEventResource::collection($this->resource['events']),
            'created_at' => $approval->created_at?->toIso8601String(),
            'updated_at' => $approval->updated_at?->toIso8601String(),
        ];
    }
}
