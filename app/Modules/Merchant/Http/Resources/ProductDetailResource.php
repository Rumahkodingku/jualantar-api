<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Product;
use Illuminate\Http\Request;

/**
 * Master product detail including its category, variants and media.
 *
 * @mixin Product
 */
class ProductDetailResource extends ProductResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'category' => new CatalogCategoryResource($this->whenLoaded('category')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'media' => ProductMediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
