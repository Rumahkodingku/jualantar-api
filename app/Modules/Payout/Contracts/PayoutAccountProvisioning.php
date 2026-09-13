<?php

namespace App\Modules\Payout\Contracts;

use App\Modules\Payout\Contracts\DataTransferObjects\PayoutAccountData;
use App\Shared\Result\Result;

/**
 * Public write seam for payout accounts.
 *
 * Consumers (e.g. Merchant registration) must never import the Payout domain
 * models; they depend on this contract and pass the owner type as a primitive
 * string. Bank validation happens inside the implementation through the
 * BankDirectory contract.
 */
interface PayoutAccountProvisioning
{
    /**
     * Create or update the primary payout account for an owner.
     *
     * Returns a Result whose ok value is a PayoutAccountData and whose error
     * uses the "invalid_payout_account" code when the bank is invalid.
     *
     * @param  string  $ownerType  one of the PayoutOwnerType values (e.g. "merchant")
     */
    public function saveForOwner(
        string $ownerType,
        string $ownerId,
        int $bankId,
        string $accountNumber,
        string $accountName,
    ): Result;
}
