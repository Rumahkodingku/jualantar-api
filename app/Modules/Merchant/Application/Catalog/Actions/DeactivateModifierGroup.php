<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;

final class DeactivateModifierGroup
{
    public function __invoke(ProductModifierGroup $group): Result
    {
        $group->update(['status' => CatalogStatus::Inactive]);

        return Result::ok($group->refresh());
    }
}
