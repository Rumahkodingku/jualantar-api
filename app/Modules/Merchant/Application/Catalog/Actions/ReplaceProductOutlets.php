<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesOutletAssignments;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ReplaceProductOutlets
{
    use ManagesOutletAssignments;

    /**
     * The submitted outlet list becomes the source of truth: outlets missing
     * from it are soft deleted, outlets already assigned keep their status,
     * availability and order untouched, and new outlets are created. An empty
     * list detaches the product from every outlet.
     *
     * @param  list<string>  $outletIds
     */
    public function __invoke(Product $product, array $outletIds): Result
    {
        return DB::transaction(function () use ($product, $outletIds): Result {
            $existing = OutletProduct::query()
                ->where('product_id', $product->id)
                ->get();

            $assignedOutletIds = $existing->pluck('outlet_id')->all();

            foreach ($existing as $assignment) {
                if (! in_array($assignment->outlet_id, $outletIds, true)) {
                    $assignment->delete();
                }
            }

            foreach ($outletIds as $outletId) {
                if (in_array($outletId, $assignedOutletIds, true)) {
                    continue;
                }

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

            return Result::ok(null);
        });
    }
}
