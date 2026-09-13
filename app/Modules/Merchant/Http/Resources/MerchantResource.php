<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Merchant
 */
class MerchantResource extends JsonResource
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
            'type' => $this->type->value,
            'status' => $this->status->value,
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
            'logo' => $this->logo,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
