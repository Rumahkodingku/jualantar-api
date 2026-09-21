<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Shared\Result\Result;

final class ActivateOutlet
{
    public function __invoke(MerchantOutlet $outlet): Result
    {
        if ($outlet->status !== OutletStatus::Active) {
            $outlet->update(['status' => OutletStatus::Active]);
        }

        return Result::ok($outlet->refresh());
    }
}
