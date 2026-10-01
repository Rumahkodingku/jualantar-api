<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesOutletItemOverrides;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

/**
 * Hide one master variant from one outlet.
 *
 * The master status stays untouched and keeps being the ceiling: the item is
 * only restricted here. The outlet assignment row is locked so two managers
 * cannot both pass the "last sellable variant" check on the same product.
 */
final class DeactivateOutletProductVariant
{
    use ManagesOutletItemOverrides;

    public function __invoke(OutletProduct $assignment, ProductVariant $variant, ?string $actorId = null): Result
    {
        return DB::transaction(function () use ($assignment, $variant, $actorId): Result {
            $locked = $this->lockAssignment($assignment);
            $product = $locked->product;

            if ($error = $this->assertOverrideAllowed($variant->status)) {
                return $error;
            }

            if ($error = $this->guardOutletLastSellableVariant($locked, $product, $variant)) {
                return $error;
            }

            return Result::ok($this->recordVariantOverride($locked, $variant, $actorId));
        });
    }
}
