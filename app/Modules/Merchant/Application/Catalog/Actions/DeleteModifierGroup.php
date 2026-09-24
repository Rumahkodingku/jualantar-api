<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class DeleteModifierGroup
{
    /**
     * Soft delete the group together with every modifier in it, in a single
     * transaction. Allowed even when the group is active.
     */
    public function __invoke(ProductModifierGroup $group): Result
    {
        return DB::transaction(function () use ($group): Result {
            ProductModifier::query()->where('modifier_group_id', $group->id)->delete();
            $group->delete();

            return Result::ok(null);
        });
    }
}
