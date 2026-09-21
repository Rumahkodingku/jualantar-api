<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Application\Operations\Concerns\TransitionsMerchantStatus;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;

final class SuspendMerchant
{
    use TransitionsMerchantStatus;

    /**
     * The optional reason is validated at the edge but intentionally not
     * persisted in P0; operational audit history is a P1 concern.
     */
    public function __invoke(Merchant $merchant): Result
    {
        return $this->transition($merchant, MerchantStatus::Suspended);
    }
}
