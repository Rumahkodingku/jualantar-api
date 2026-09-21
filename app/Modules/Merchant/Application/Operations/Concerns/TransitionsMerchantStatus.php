<?php

namespace App\Modules\Merchant\Application\Operations\Concerns;

use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;

trait TransitionsMerchantStatus
{
    use ReportsOperationsErrors;

    private function transition(Merchant $merchant, MerchantStatus $target): Result
    {
        if (! $merchant->status->canTransitionTo($target)) {
            return $this->invalidStatusTransition($merchant->status, $target);
        }

        $merchant->update(['status' => $target]);

        return Result::ok($merchant->refresh());
    }
}
