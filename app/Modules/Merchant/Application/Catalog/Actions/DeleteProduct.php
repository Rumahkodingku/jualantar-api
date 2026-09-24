<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class DeleteProduct
{
    /**
     * Soft delete the master product together with its variants, media, outlet
     * assignments, modifier groups and their modifiers in a single transaction.
     */
    public function __invoke(Product $product): Result
    {
        return DB::transaction(function () use ($product): Result {
            ProductVariant::query()->where('product_id', $product->id)->delete();
            ProductMedia::query()->where('product_id', $product->id)->delete();
            OutletProduct::query()->where('product_id', $product->id)->delete();

            $groupIds = ProductModifierGroup::query()->where('product_id', $product->id)->pluck('id');
            ProductModifier::query()->whereIn('modifier_group_id', $groupIds)->delete();
            ProductModifierGroup::query()->where('product_id', $product->id)->delete();

            $product->delete();

            return Result::ok(null);
        });
    }
}
