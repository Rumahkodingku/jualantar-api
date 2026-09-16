<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Merchant
 */
class MerchantDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type?->value,
            'status' => $this->status->value,
            'logo' => $this->logo,
            'logo_url' => $this->logo_url,
            'service' => $this->service === null ? null : [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'slug' => $this->service->slug,
            ],
            'owner' => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'email' => $this->owner->email,
                'phone' => $this->owner->phone,
            ],
            'identity' => MerchantIdentityResource::make($this->whenLoaded('identity')),
            'legal_entity' => LegalEntityResource::make($this->whenLoaded('legalEntity')),
            'categories' => MerchantCategoryResource::collection($this->whenLoaded('categories')),
            'outlets' => MerchantOutletResource::collection($this->whenLoaded('outlets')),
            'documents' => MerchantDocumentResource::collection($this->whenLoaded('documents')),
            'payout_accounts' => $this->payout_accounts,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
