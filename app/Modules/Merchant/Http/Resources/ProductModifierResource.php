<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\ProductModifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A master customization option.
 *
 * `outlet_overrides` follows the same rule as on the variant resource: only
 * the master product detail eager loads the relation, so the key is absent on
 * the narrower endpoints.
 *
 * @mixin ProductModifier
 */
class ProductModifierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'is_default' => (bool) $this->is_default,
            'status' => $this->status->value,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'outlet_overrides' => OutletItemOverrideResource::collection(
                $this->whenLoaded('outletStatusOverrides'),
            ),
        ];
    }
}
