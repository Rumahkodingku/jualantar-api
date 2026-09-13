<?php

namespace App\Modules\Payout\Infrastructure\Repositories;

use App\Modules\Payout\Contracts\DataTransferObjects\PayoutAccountData;
use App\Modules\Payout\Contracts\PayoutAccountLookup;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Models\PayoutAccount;

final class EloquentPayoutAccountLookup implements PayoutAccountLookup
{
    /**
     * @return list<PayoutAccountData>
     */
    public function accountsForOwner(string $ownerType, string $ownerId): array
    {
        $owner = PayoutOwnerType::tryFrom($ownerType);

        if ($owner === null) {
            return [];
        }

        return PayoutAccount::query()
            ->forOwner($owner, $ownerId)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PayoutAccount $account): PayoutAccountData => new PayoutAccountData(
                id: $account->id,
                ownerType: $account->owner_type->value,
                ownerId: $account->owner_id,
                bankId: $account->bank_id,
                accountNumber: $account->account_number,
                accountName: $account->account_name,
                isPrimary: $account->is_primary,
                status: $account->status->value,
                rejectionReason: $account->rejection_reason,
            ))
            ->all();
    }
}
