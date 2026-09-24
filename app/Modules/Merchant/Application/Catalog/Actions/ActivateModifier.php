<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;

final class ActivateModifier
{
    /**
     * Activating a modifier never breaks the active-group invariant, so no
     * check is needed here.
     */
    public function __invoke(ProductModifierGroup $group, ProductModifier $modifier): Result
    {
        $modifier->update(['status' => CatalogStatus::Active]);

        return Result::ok($modifier->refresh());
    }
}
