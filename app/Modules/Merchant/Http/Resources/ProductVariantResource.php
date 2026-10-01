<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A master variant.
 *
 * `outlet_overrides` is present only on the master product detail, where the
 * owner needs to know which outlets hid this variant and who did it. The
 * variant list endpoints never eager load the relation, so the key is absent
 * there rather than always empty.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->price,
            'status' => $this->status->value,
            'is_default' => (bool) $this->is_default,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'outlet_overrides' => OutletItemOverrideResource::collection(
                $this->whenLoaded('outletStatusOverrides'),
            ),
        ];
    }
}
