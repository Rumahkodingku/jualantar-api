<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Catalog\OutletItemStatus;
use App\Modules\Merchant\Domain\Catalog\ProductSellability;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the effective outlet catalog: the master product plus this
 * outlet's assignment and the computed `is_sellable` flag.
 *
 * Every item carries both its master `status` and the `effective_status` it has
 * at this outlet, plus `is_overridden` so a client can tell "the owner turned
 * this off" apart from "this outlet hid it". The two are equal in the list,
 * which only carries effective-active items, and differ in the detail, which
 * also carries the hidden ones.
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

        $variantOverrides = $this->statusMap($product, 'outletVariantOverrides', 'product_variant_id');
        $groupOverrides = $this->statusMap($product, 'outletModifierGroupOverrides', 'product_modifier_group_id');
        $modifierOverrides = $this->statusMap($product, 'outletModifierOverrides', 'product_modifier_id');

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
                ? $product->variants->map(fn (ProductVariant $variant): array => $this->variantPayload($variant, $variantOverrides))->values()->all()
                : [],
            'primary_media' => $primaryMedia instanceof ProductMedia ? [
                'url' => $primaryMedia->url,
                'alt_text' => $primaryMedia->alt_text,
            ] : null,
            'modifier_groups' => $product->modifierGroups
                ->map(fn (ProductModifierGroup $group): array => $this->modifierGroupPayload($group, $groupOverrides, $modifierOverrides))
                ->values()
                ->all(),
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
                activeVariantCount: $this->effectiveActiveVariantCount($product, $variantOverrides),
            ),
        ];
    }

    /**
     * @param  array<string, CatalogStatus>  $overrides
     * @return array<string, mixed>
     */
    private function variantPayload(ProductVariant $variant, array $overrides): array
    {
        $override = $overrides[$variant->id] ?? null;

        return [
            'id' => $variant->id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'price' => $variant->price,
            'status' => $variant->status->value,
            'effective_status' => OutletItemStatus::resolve($variant->status, $override)->value,
            'is_overridden' => OutletItemStatus::isOverridden($override),
            'is_default' => (bool) $variant->is_default,
        ];
    }

    /**
     * @param  array<string, CatalogStatus>  $groupOverrides
     * @param  array<string, CatalogStatus>  $modifierOverrides
     * @return array<string, mixed>
     */
    private function modifierGroupPayload(
        ProductModifierGroup $group,
        array $groupOverrides,
        array $modifierOverrides,
    ): array {
        $override = $groupOverrides[$group->id] ?? null;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'selection_type' => $group->selection_type->value,
            'min_selection' => $group->min_selection,
            'max_selection' => $group->max_selection,
            'is_required' => (bool) $group->is_required,
            'status' => $group->status->value,
            'effective_status' => OutletItemStatus::resolve($group->status, $override)->value,
            'is_overridden' => OutletItemStatus::isOverridden($override),
            'display_order' => $group->display_order,
            'created_at' => $group->created_at?->toIso8601String(),
            'updated_at' => $group->updated_at?->toIso8601String(),
            'modifiers' => $group->modifiers
                ->map(function (ProductModifier $modifier) use ($modifierOverrides): array {
                    $modifierOverride = $modifierOverrides[$modifier->id] ?? null;

                    return [
                        'id' => $modifier->id,
                        'name' => $modifier->name,
                        'description' => $modifier->description,
                        'price' => $modifier->price,
                        'status' => $modifier->status->value,
                        'effective_status' => OutletItemStatus::resolve($modifier->status, $modifierOverride)->value,
                        'is_overridden' => OutletItemStatus::isOverridden($modifierOverride),
                        'is_default' => (bool) $modifier->is_default,
                        'display_order' => $modifier->display_order,
                        'created_at' => $modifier->created_at?->toIso8601String(),
                        'updated_at' => $modifier->updated_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * Variants that are sellable at this outlet, i.e. active in the master and
     * not hidden by an override.
     *
     * @param  array<string, CatalogStatus>  $overrides
     */
    private function effectiveActiveVariantCount(Product $product, array $overrides): int
    {
        return $product->variants
            ->filter(fn (ProductVariant $variant): bool => OutletItemStatus::isEffectiveActive(
                $variant->status,
                $overrides[$variant->id] ?? null,
            ))
            ->count();
    }

    /**
     * @return array<string, CatalogStatus>
     */
    private function statusMap(Product $product, string $relation, string $itemColumn): array
    {
        if (! $product->relationLoaded($relation)) {
            return [];
        }

        $overrides = $product->{$relation};

        if ($overrides === null || $overrides->isEmpty()) {
            return [];
        }

        $map = [];

        foreach ($overrides as $override) {
            /** @var Model $override */
            $status = $override->status;

            if ($status instanceof CatalogStatus) {
                $map[(string) $override->{$itemColumn}] = $status;
            }
        }

        return $map;
    }
}
