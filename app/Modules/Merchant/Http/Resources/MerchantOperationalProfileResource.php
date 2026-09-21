<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Merchant
 */
class MerchantOperationalProfileResource extends JsonResource
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
            'logo' => $this->logo,
            'logo_url' => $this->logo_url,
            'operational_phone' => $this->operational_phone,
            'operational_email' => $this->operational_email,
            'website' => $this->website,
            'status' => $this->status->value,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
