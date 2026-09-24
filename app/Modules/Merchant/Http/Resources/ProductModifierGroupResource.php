<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductModifierGroup
 */
class ProductModifierGroupResource extends JsonResource
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
            'selection_type' => $this->selection_type->value,
            'min_selection' => $this->min_selection,
            'max_selection' => $this->max_selection,
            'is_required' => (bool) $this->is_required,
            'status' => $this->status->value,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'modifiers' => ProductModifierResource::collection($this->whenLoaded('modifiers')),
        ];
    }
}
