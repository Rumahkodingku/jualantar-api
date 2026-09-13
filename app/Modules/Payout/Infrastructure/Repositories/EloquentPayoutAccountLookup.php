<?php

namespace App\Modules\Payout\Infrastructure\Repositories;

use App\Modules\BankDirectory\Contracts\BankLookup;
use App\Modules\Payout\Contracts\DataTransferObjects\PayoutAccountData;
use App\Modules\Payout\Contracts\PayoutAccountLookup;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Models\PayoutAccount;

final class EloquentPayoutAccountLookup implements PayoutAccountLookup
{
    public function __construct(private readonly BankLookup $bankLookup) {}

    /**
     * @return list<PayoutAccountData>
     */
    public function accountsForOwner(string $ownerType, string $ownerId): array
    {
        $owner = PayoutOwnerType::tryFrom($ownerType);

        if ($owner === null) {
            return [];
        }

        $accounts = PayoutAccount::query()
            ->forOwner($owner, $ownerId)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        $banks = $this->bankLookup->banks(
            $accounts->pluck('bank_id')->unique()->values()->all(),
        );

        return $accounts
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
                bankName: $banks[$account->bank_id]->name ?? null,
            ))
            ->all();
    }
}
