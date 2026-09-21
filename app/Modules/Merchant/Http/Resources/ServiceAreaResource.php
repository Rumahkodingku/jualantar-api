<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Enums\OutletServiceAreaType;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MerchantOutlet
 */
class ServiceAreaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->service_area_type;

        $data = ['type' => $type->value];

        if ($type === OutletServiceAreaType::Radius) {
            $data['radius_km'] = $this->service_radius_km;

            return $data;
        }

        $field = $type->value.'_id';
        $data[$field] = $this->{$field};

        return $data;
    }
}
