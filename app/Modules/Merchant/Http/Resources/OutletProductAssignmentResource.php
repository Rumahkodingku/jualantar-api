<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\OutletProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product's assignment to one outlet. The `outlet` summary is included when
 * the relation is loaded.
 *
 * @mixin OutletProduct
 */
class OutletProductAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'outlet_id' => $this->outlet_id,
            'outlet' => $this->whenLoaded('outlet', fn (): array => [
                'id' => $this->outlet->id,
                'name' => $this->outlet->name,
                'status' => $this->outlet->status->value,
            ]),
            'status' => $this->status->value,
            'availability_status' => $this->availability_status->value,
            'unavailable_reason' => $this->unavailable_reason,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
