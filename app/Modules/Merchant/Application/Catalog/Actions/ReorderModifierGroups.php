<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ReorderModifierGroups
{
    use ReportsCatalogErrors;

    /**
     * @param  list<array{group_id: string, display_order: int}>  $items
     */
    public function __invoke(Product $product, array $items): Result
    {
        return DB::transaction(function () use ($product, $items): Result {
            $ids = array_map(fn (array $item): string => $item['group_id'], $items);

            $inScope = ProductModifierGroup::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $ids)
                ->count();

            if ($inScope !== count($ids)) {
                return $this->catalogValidationError([
                    'items' => ['One or more modifier groups are outside this product scope.'],
                ]);
            }

            foreach ($items as $item) {
                ProductModifierGroup::query()
                    ->whereKey($item['group_id'])
                    ->update(['display_order' => $item['display_order']]);
            }

            return Result::ok(null);
        });
    }
}
