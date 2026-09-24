<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Shared\Result\Result;

final class RemoveProductOutletAssignment
{
    /**
     * Detach the product from an outlet. The master product is untouched and a
     * later assign creates a fresh record.
     */
    public function __invoke(OutletProduct $assignment): Result
    {
        $assignment->delete();

        return Result::ok(null);
    }
}
