<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\ProductMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductMedia
 */
class ProductMediaResource extends JsonResource
{
    /**
     * `url` is hydrated by the controller from the storage abstraction; it is
     * null when the temporary URL cannot be generated.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'alt_text' => $this->alt_text,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'is_primary' => (bool) $this->is_primary,
            'display_order' => $this->display_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
