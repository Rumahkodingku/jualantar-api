<?php

namespace App\Modules\Geography\Application\Actions;

use App\Shared\Result\Result;
use Illuminate\Database\Eloquent\Model;

final class SetRegionActiveStatus
{
    /**
     * Idempotently set a region's own activation flag.
     */
    public function __invoke(Model $region, bool $isActive): Result
    {
        if ($region->getAttribute('is_active') !== $isActive) {
            $region->update(['is_active' => $isActive]);
        }

        return Result::ok($region->refresh());
    }
}
