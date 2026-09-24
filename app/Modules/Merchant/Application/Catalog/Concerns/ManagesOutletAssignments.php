<?php

namespace App\Modules\Merchant\Application\Catalog\Concerns;

use App\Modules\Merchant\Domain\Models\OutletProduct;

trait ManagesOutletAssignments
{
    use ReportsCatalogErrors;

    private function nextDisplayOrder(string $outletId): int
    {
        return (int) OutletProduct::query()
            ->where('outlet_id', $outletId)
            ->max('display_order') + 1;
    }
}
