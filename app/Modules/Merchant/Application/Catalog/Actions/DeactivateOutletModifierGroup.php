<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesOutletItemOverrides;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

/**
 * Hide one master customization group from one outlet.
 *
 * The group's options are not touched: the group simply stops being shown at
 * this outlet, and hiding individual options while the group is hidden stays
 * allowed on purpose.
 */
final class DeactivateOutletModifierGroup
{
    use ManagesOutletItemOverrides;

    public function __invoke(
        OutletProduct $assignment,
        ProductModifierGroup $group,
        ?string $actorId = null,
    ): Result {
        return DB::transaction(function () use ($assignment, $group, $actorId): Result {
            $locked = $this->lockAssignment($assignment);

            if ($error = $this->assertOverrideAllowed($group->status)) {
                return $error;
            }

            return Result::ok($this->recordModifierGroupOverride($locked, $group, $actorId));
        });
    }
}
