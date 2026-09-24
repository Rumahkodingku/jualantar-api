<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Shared\Result\Result;

final class DeactivateProductOutletAssignment
{
    public function __invoke(OutletProduct $assignment): Result
    {
        $assignment->update(['status' => CatalogStatus::Inactive]);

        return Result::ok($assignment->refresh());
    }
}
