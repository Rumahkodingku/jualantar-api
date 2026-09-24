<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesOutletAssignments;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class AssignProductOutlets
{
    use ManagesOutletAssignments;

    /**
     * Assign a product to one or more outlets, all or nothing: when any of the
     * outlets already has the product assigned nothing is written.
     *
     * @param  list<string>  $outletIds
     */
    public function __invoke(Product $product, array $outletIds): Result
    {
        return DB::transaction(function () use ($product, $outletIds): Result {
            $alreadyAssigned = OutletProduct::query()
                ->where('product_id', $product->id)
                ->whereIn('outlet_id', $outletIds)
                ->exists();

            if ($alreadyAssigned) {
                return $this->duplicateOutletAssignment();
            }

            foreach ($outletIds as $outletId) {
                $this->assign($product, $outletId);
            }

            return Result::ok(null);
        });
    }

    private function assign(Product $product, string $outletId): void
    {
        OutletProduct::query()->create([
            'merchant_id' => $product->merchant_id,
            'outlet_id' => $outletId,
            'product_id' => $product->id,
            'status' => CatalogStatus::Active,
            'availability_status' => ProductAvailabilityStatus::Available,
            'unavailable_reason' => null,
            'display_order' => $this->nextDisplayOrder($outletId),
        ]);
    }
}
