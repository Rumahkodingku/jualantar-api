<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Shared\Result\Result;

final class DeleteMerchantOutlet
{
    use ReportsRegistrationErrors;

    public function __invoke(Merchant $merchant, MerchantOutlet $outlet): Result
    {
        if ($error = $this->requireDraft($merchant)) {
            return $error;
        }

        if ($outlet->merchant_id !== $merchant->id) {
            return $this->registrationNotFound();
        }

        $outlet->delete();

        return Result::ok(null);
    }
}
