<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Models\OutletProductModifier;
use App\Modules\Merchant\Domain\Models\OutletProductModifierGroup;
use App\Modules\Merchant\Domain\Models\OutletProductVariant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Shared\Result\Result;

/**
 * Drop every outlet override of one master item, at every outlet.
 *
 * This is the owner's counterpart to a manager's per-outlet reset: it answers
 * "which outlets changed this?" with "none of them any more". The master status
 * is untouched, so an item that the owner left inactive stays inactive.
 */
final class ClearOutletItemOverrides
{
    /**
     * @return Result<array{variants: int, modifier_groups: int, modifiers: int}>
     */
    public function forVariant(Product $product, string $variantId): Result
    {
        return Result::ok([
            'variants' => OutletProductVariant::query()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->delete(),
            'modifier_groups' => 0,
            'modifiers' => 0,
        ]);
    }

    /**
     * @return Result<array{variants: int, modifier_groups: int, modifiers: int}>
     */
    public function forModifierGroup(Product $product, string $groupId): Result
    {
        return Result::ok([
            'variants' => 0,
            'modifier_groups' => OutletProductModifierGroup::query()
                ->where('product_id', $product->id)
                ->where('product_modifier_group_id', $groupId)
                ->delete(),
            'modifiers' => OutletProductModifier::query()
                ->where('product_id', $product->id)
                ->where('product_modifier_group_id', $groupId)
                ->delete(),
        ]);
    }

    /**
     * @return Result<array{variants: int, modifier_groups: int, modifiers: int}>
     */
    public function forModifier(Product $product, string $modifierId): Result
    {
        return Result::ok([
            'variants' => 0,
            'modifier_groups' => 0,
            'modifiers' => OutletProductModifier::query()
                ->where('product_id', $product->id)
                ->where('product_modifier_id', $modifierId)
                ->delete(),
        ]);
    }
}
