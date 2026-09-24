<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesModifierGroups;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ActivateModifierGroup
{
    use ManagesModifierGroups;

    /**
     * A group may only be activated when it exposes at least
     * max(min_selection, 1) active modifiers. The group row is locked so a
     * concurrent modifier deactivation cannot slip between the check and the
     * update.
     */
    public function __invoke(ProductModifierGroup $group): Result
    {
        return DB::transaction(function () use ($group): Result {
            $locked = $this->lockGroup($group);

            if ($error = $this->assertGroupInvariant($locked)) {
                return $error;
            }

            $locked->update(['status' => CatalogStatus::Active]);

            return Result::ok($locked->refresh());
        });
    }
}
