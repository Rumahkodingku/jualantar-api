<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MerchantApproval
 */
class MerchantApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'application' => MerchantApplicationResource::make($this->whenLoaded('application')),
            'merchant' => $this->whenLoaded('application', fn (): ?array => $this->merchantPayload()),
            'assigned_to' => $this->assigned_to,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'decision' => $this->decision?->value,
            'decision_reason' => $this->decision_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function merchantPayload(): ?array
    {
        $application = $this->application;

        if ($application === null || ! $application->relationLoaded('merchant')) {
            return null;
        }

        $merchant = $application->merchant;

        if (! $merchant instanceof Merchant) {
            return null;
        }

        return [
            'id' => $merchant->id,
            'business_name' => $merchant->business_name,
            'slug' => $merchant->slug,
            'type' => $merchant->type?->value,
            'service' => $merchant->service === null ? null : [
                'id' => $merchant->service->id,
                'name' => $merchant->service->name,
                'slug' => $merchant->service->slug,
            ],
        ];
    }
}
