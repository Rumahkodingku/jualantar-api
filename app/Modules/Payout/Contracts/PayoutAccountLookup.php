<?php

namespace App\Modules\Payout\Contracts;

use App\Modules\Payout\Contracts\DataTransferObjects\PayoutAccountData;

/**
 * Public read seam for payout accounts.
 *
 * Consumers (e.g. Merchant) must never import the Payout domain models; they
 * depend on this contract and pass the owner type as a primitive string.
 */
interface PayoutAccountLookup
{
    /**
     * @param  string  $ownerType  one of the PayoutOwnerType values (e.g. "merchant")
     * @return list<PayoutAccountData>
     */
    public function accountsForOwner(string $ownerType, string $ownerId): array;
}
