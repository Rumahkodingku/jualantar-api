<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesOutletItemOverrides;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

/**
 * Hide one master customization option from one outlet.
 *
 * When the option is the last one an effective-active group needs here, the
 * write is refused: the manager should hide the whole group instead, or turn
 * the product off at this outlet.
 */
final class DeactivateOutletModifier
{
    use ManagesOutletItemOverrides;

    public function __invoke(
        OutletProduct $assignment,
        ProductModifierGroup $group,
        ProductModifier $modifier,
        ?string $actorId = null,
    ): Result {
        return DB::transaction(function () use ($assignment, $group, $modifier, $actorId): Result {
            $locked = $this->lockAssignment($assignment);

            if ($error = $this->assertOverrideAllowed($modifier->status)) {
                return $error;
            }

            if ($error = $this->guardOutletModifierSatisfiable($locked, $group, $modifier)) {
                return $error;
            }

            return Result::ok($this->recordModifierOverride($locked, $modifier, $actorId));
        });
    }
}
