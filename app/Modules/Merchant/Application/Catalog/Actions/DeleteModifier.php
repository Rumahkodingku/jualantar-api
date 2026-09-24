<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesModifierGroups;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class DeleteModifier
{
    use ManagesModifierGroups;

    /**
     * Deleting an active modifier on an active group is refused when it would
     * leave the group unable to satisfy its min selection. On an inactive
     * group it is always allowed.
     */
    public function __invoke(ProductModifierGroup $group, ProductModifier $modifier): Result
    {
        return DB::transaction(function () use ($group, $modifier): Result {
            $locked = $this->lockGroup($group);

            if ($error = $this->assertModifierRemovalKeepsGroupSatisfiable($locked, $modifier)) {
                return $error;
            }

            $this->resetDefault($modifier);
            $modifier->delete();

            return Result::ok(null);
        });
    }
}
