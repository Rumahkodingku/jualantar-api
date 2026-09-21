<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Application\Operations\Concerns\TransitionsMerchantStatus;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;

final class ActivateMerchant
{
    use TransitionsMerchantStatus;

    public function __invoke(Merchant $merchant): Result
    {
        return $this->transition($merchant, MerchantStatus::Active);
    }
}
