<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductVariants;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ReorderProductVariants
{
    use ManagesProductVariants;

    /**
     * @param  list<array{variant_id: string, display_order: int}>  $items
     */
    public function __invoke(Product $product, array $items): Result
    {
        return DB::transaction(function () use ($product, $items): Result {
            $ids = array_map(fn (array $item): string => $item['variant_id'], $items);

            $inScope = ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $ids)
                ->count();

            if ($inScope !== count($ids)) {
                return $this->catalogValidationError([
                    'items' => ['One or more variants do not belong to this product.'],
                ]);
            }

            foreach ($items as $item) {
                ProductVariant::query()
                    ->whereKey($item['variant_id'])
                    ->update(['display_order' => $item['display_order']]);
            }

            return Result::ok(null);
        });
    }
}
