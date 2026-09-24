<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductMedia;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ReorderProductMedia
{
    use ManagesProductMedia;

    /**
     * @param  list<array{media_id: string, display_order: int}>  $items
     */
    public function __invoke(Product $product, array $items): Result
    {
        return DB::transaction(function () use ($product, $items): Result {
            $ids = array_map(fn (array $item): string => $item['media_id'], $items);

            $inScope = ProductMedia::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $ids)
                ->count();

            if ($inScope !== count($ids)) {
                return $this->catalogValidationError([
                    'items' => ['One or more media do not belong to this product.'],
                ]);
            }

            foreach ($items as $item) {
                ProductMedia::query()
                    ->whereKey($item['media_id'])
                    ->update(['display_order' => $item['display_order']]);
            }

            return Result::ok(null);
        });
    }
}
