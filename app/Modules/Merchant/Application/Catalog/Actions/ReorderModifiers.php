<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ReorderModifiers
{
    use ReportsCatalogErrors;

    /**
     * @param  list<array{modifier_id: string, display_order: int}>  $items
     */
    public function __invoke(ProductModifierGroup $group, array $items): Result
    {
        return DB::transaction(function () use ($group, $items): Result {
            $ids = array_map(fn (array $item): string => $item['modifier_id'], $items);

            $inScope = ProductModifier::query()
                ->where('modifier_group_id', $group->id)
                ->whereIn('id', $ids)
                ->count();

            if ($inScope !== count($ids)) {
                return $this->catalogValidationError([
                    'items' => ['One or more modifiers are outside this group scope.'],
                ]);
            }

            foreach ($items as $item) {
                ProductModifier::query()
                    ->whereKey($item['modifier_id'])
                    ->update(['display_order' => $item['display_order']]);
            }

            return Result::ok(null);
        });
    }
}
