<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesOutletItemOverrides;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\OutletProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

/**
 * Make one customization option follow the master catalog again at one outlet.
 *
 * Adding an option back can only help the group invariant, so no guard runs
 * here.
 */
final class ResetOutletModifier
{
    use ManagesOutletItemOverrides;

    public function __invoke(
        OutletProduct $assignment,
        ProductModifierGroup $group,
        ProductModifier $modifier,
    ): Result {
        return DB::transaction(function () use ($assignment, $modifier): Result {
            // Same lock as on deactivate, so a concurrent option write cannot
            // slip between the invariant check and the delete.
            $locked = $this->lockAssignment($assignment);

            OutletProductModifier::query()
                ->where('outlet_id', $locked->outlet_id)
                ->where('product_modifier_id', $modifier->id)
                ->delete();

            return Result::ok($modifier);
        });
    }
}
