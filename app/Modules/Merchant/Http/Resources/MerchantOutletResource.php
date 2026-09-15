<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MerchantOutlet
 */
class MerchantOutletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'province_id' => $this->province_id,
            'regency_id' => $this->regency_id,
            'district_id' => $this->district_id,
            'village_id' => $this->village_id,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'service_area_type' => $this->service_area_type->value,
            'service_radius_km' => $this->service_radius_km,
            'operating_hours' => $this->operating_hours,
            'photos' => $this->photos ?? [],
            'photos_url' => $this->photos_url ?? [],
            'status' => $this->status->value,
            'geography' => $this->geography === null ? null : [
                'village' => $this->geography->village,
                'district' => $this->geography->district,
                'regency' => $this->geography->regency,
                'province' => $this->geography->province,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
