<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->category(),
            'name' => $this->name,
            'description' => $this->description,
            'product_type' => $this->product_type->value,
            'price' => $this->price,
            'status' => $this->status->value,
            'display_order' => $this->display_order,
            'primary_media' => $this->primaryMedia(),
            ...$this->summary(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Null unless the caller eager loaded the category, so read paths opt in to
     * the extra query instead of the resource issuing one.
     *
     * @return array{id: string, name: string, status: string}|null
     */
    private function category(): ?array
    {
        if (! $this->resource->relationLoaded('category')) {
            return null;
        }

        $category = $this->resource->category;

        return $category === null ? null : [
            'id' => $category->id,
            'name' => $category->name,
            'status' => $category->status->value,
        ];
    }

    /**
     * Null unless the caller eager loaded and hydrated the primary media, so
     * read paths opt in to the extra query instead of the resource issuing one.
     *
     * @return array{url: string|null, alt_text: string|null}|null
     */
    private function primaryMedia(): ?array
    {
        if (! $this->resource->relationLoaded('media')) {
            return null;
        }

        $media = $this->resource->media->firstWhere('is_primary', true);

        return $media instanceof ProductMedia
            ? ['url' => $media->url, 'alt_text' => $media->alt_text]
            : null;
    }

    /**
     * Counts the card renders. They arrive as sub-select attributes rather than
     * relations, so they only exist when the caller asked for them; the detail
     * endpoint ships the full collections instead and omits these entirely.
     *
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $product = $this->resource;
        $summary = [];

        if (isset($product->variants_count)) {
            $summary['variants_count'] = (int) $product->variants_count;
            $summary['min_price'] = $product->min_price;
        }

        if (isset($product->media_count)) {
            $summary['media_count'] = (int) $product->media_count;
        }

        if (isset($product->modifier_groups_count)) {
            $summary['modifier_groups_count'] = (int) $product->modifier_groups_count;
        }

        return $summary;
    }
}
