<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesOutletItemOverrides;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\OutletProductVariant;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

/**
 * Make one variant follow the master catalog again at one outlet.
 *
 * "Activating" an item at an outlet is not a promotion: the override row is
 * dropped, so the item goes back to whatever the owner set. That is why no
 * invariant can break here — adding a variant back can only increase the number
 * of sellable variants.
 */
final class ResetOutletProductVariant
{
    use ManagesOutletItemOverrides;

    public function __invoke(OutletProduct $assignment, ProductVariant $variant): Result
    {
        return DB::transaction(function () use ($assignment, $variant): Result {
            // The assignment row is locked for the same reason as on deactivate:
            // two managers acting on the same outlet must not interleave.
            $locked = $this->lockAssignment($assignment);

            OutletProductVariant::query()
                ->where('outlet_id', $locked->outlet_id)
                ->where('product_variant_id', $variant->id)
                ->delete();

            return Result::ok($variant);
        });
    }
}
