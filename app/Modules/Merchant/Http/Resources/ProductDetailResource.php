<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
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
            'summary' => $this->detailSummary(),
            'category' => new CatalogCategoryResource($this->whenLoaded('category')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'media' => ProductMediaResource::collection($this->whenLoaded('media')),
            'modifier_groups' => ProductModifierGroupResource::collection($this->whenLoaded('modifierGroups')),
        ];
    }

    /**
     * Presentation summary the detail header and navigation read directly.
     *
     * Counts and the minimum price are derived from the relations this endpoint
     * already eager loads, so the resource never issues a query; only the outlet
     * assignment count arrives as a `loadCount` attribute from the controller.
     *
     * @return array<string, mixed>
     */
    private function detailSummary(): array
    {
        return [
            'price' => $this->detailPrice(),
            'variants_count' => $this->relationCount('variants'),
            'customization_groups_count' => $this->relationCount('modifierGroups'),
            'media_count' => $this->relationCount('media'),
            'outlets_count' => (int) ($this->resource->outlets_count ?? 0),
        ];
    }

    /**
     * @return array{type: string, value: float|null}
     */
    private function detailPrice(): array
    {
        if ($this->product_type === ProductType::Simple) {
            return ['type' => 'fixed', 'value' => (float) $this->price];
        }

        return ['type' => 'from', 'value' => $this->minimumActiveVariantPrice()];
    }

    private function minimumActiveVariantPrice(): ?float
    {
        if (! $this->resource->relationLoaded('variants')) {
            return null;
        }

        $prices = $this->resource->variants
            ->where('status', CatalogStatus::Active)
            ->map(static fn ($variant): float => (float) $variant->price);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    private function relationCount(string $relation): int
    {
        return $this->resource->relationLoaded($relation)
            ? $this->resource->{$relation}->count()
            : 0;
    }
}
