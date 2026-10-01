<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesOutletItemOverrides;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\OutletProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

/**
 * Make one customization group follow the master catalog again at one outlet.
 *
 * Unlike a variant this can fail: while the group was hidden its options could
 * individually have been hidden too, so making the group visible again may
 * leave it with fewer selectable options than its min selection requires.
 *
 * The invariant is checked *before* the override is dropped. That is equivalent
 * to checking it after, because the group's satisfiability depends only on the
 * master status and on the option overrides — never on the group's own
 * override — and it keeps a refused reset from persisting its own deletion.
 */
final class ResetOutletModifierGroup
{
    use ManagesOutletItemOverrides;

    public function __invoke(OutletProduct $assignment, ProductModifierGroup $group): Result
    {
        return DB::transaction(function () use ($assignment, $group): Result {
            $locked = $this->lockAssignment($assignment);

            if ($error = $this->assertOutletGroupSatisfiable($locked, $group)) {
                return $error;
            }

            OutletProductModifierGroup::query()
                ->where('outlet_id', $locked->outlet_id)
                ->where('product_modifier_group_id', $group->id)
                ->delete();

            return Result::ok($group);
        });
    }
}
