<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ReorderCategories
{
    use ReportsCatalogErrors;

    /**
     * @param  list<array{category_id: string, display_order: int}>  $items
     */
    public function __invoke(Merchant $merchant, array $items): Result
    {
        return DB::transaction(function () use ($merchant, $items): Result {
            $ids = array_map(fn (array $item): string => $item['category_id'], $items);

            $inScope = CatalogCategory::query()
                ->where('merchant_id', $merchant->id)
                ->whereIn('id', $ids)
                ->count();

            if ($inScope !== count($ids)) {
                return $this->catalogValidationError([
                    'items' => ['One or more categories are outside your merchant scope.'],
                ]);
            }

            foreach ($items as $item) {
                CatalogCategory::query()
                    ->whereKey($item['category_id'])
                    ->update(['display_order' => $item['display_order']]);
            }

            return Result::ok(null);
        });
    }
}
