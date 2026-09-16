<?php

namespace App\Modules\Payout\Contracts;

use App\Shared\Result\Result;

/**
 * Public write seam for verifying or rejecting a payout account.
 *
 * The Payout module owns payout verification. Consumers (e.g. Merchant
 * Approval) must never touch the Payout domain directly; they call this
 * contract and pass the verifying user id as a primitive.
 */
interface PayoutAccountVerification
{
    /**
     * Mark a pending payout account as verified/active.
     */
    public function markVerified(string $payoutAccountId, string $verifiedBy): Result;

    /**
     * Reject a pending payout account with a reason.
     */
    public function markRejected(string $payoutAccountId, string $reason, string $verifiedBy): Result;
}
