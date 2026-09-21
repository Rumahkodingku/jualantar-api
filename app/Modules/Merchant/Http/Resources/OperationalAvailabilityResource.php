<?php

namespace App\Modules\Merchant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class OperationalAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'reason' => $this->resource['reason'],
            'merchant_status' => $this->resource['merchant_status'],
            'outlet_status' => $this->resource['outlet_status'],
            'schedule' => $this->resource['schedule'],
        ];
    }
}
