<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Catalog\ProductSellability;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the effective outlet catalog: the master product plus this
 * outlet's assignment and the computed `is_sellable` flag.
 *
 * @mixin Product
 */
class OutletCatalogItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        /** @var OutletProduct|null $assignment */
        $assignment = $product->outletProducts->first();
        $primaryMedia = $product->media->firstWhere('is_primary', true);

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'product_type' => $product->product_type->value,
                'price' => $product->price,
                'status' => $product->status->value,
            ],
            'category' => $product->category === null ? null : [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'status' => $product->category->status->value,
            ],
            'variants' => $product->product_type === ProductType::Variable
                ? $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'status' => $variant->status->value,
                    'is_default' => (bool) $variant->is_default,
                ])->values()->all()
                : [],
            'primary_media' => $primaryMedia instanceof ProductMedia ? [
                'url' => $primaryMedia->url,
                'alt_text' => $primaryMedia->alt_text,
            ] : null,
            'modifier_groups' => ProductModifierGroupResource::collection($product->modifierGroups),
            'assignment' => $assignment === null ? null : [
                'id' => $assignment->id,
                'status' => $assignment->status->value,
                'availability_status' => $assignment->availability_status->value,
                'unavailable_reason' => $assignment->unavailable_reason,
                'display_order' => $assignment->display_order,
            ],
            'is_sellable' => $assignment !== null && ProductSellability::evaluate(
                productStatus: $product->status,
                categoryStatus: $product->category?->status,
                assignmentStatus: $assignment->status,
                availabilityStatus: $assignment->availability_status,
                productType: $product->product_type,
                activeVariantCount: $product->variants
                    ->where('status', CatalogStatus::Active)
                    ->count(),
            ),
        ];
    }
}
