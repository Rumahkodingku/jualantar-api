<?php

namespace App\Modules\Payout\Infrastructure\Repositories;

use App\Modules\BankDirectory\Contracts\BankLookup;
use App\Modules\Payout\Contracts\DataTransferObjects\PayoutAccountData;
use App\Modules\Payout\Contracts\PayoutAccountProvisioning;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Enums\PayoutStatus;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentPayoutAccountProvisioning implements PayoutAccountProvisioning
{
    public function __construct(private readonly BankLookup $bankLookup) {}

    public function saveForOwner(
        string $ownerType,
        string $ownerId,
        int $bankId,
        string $accountNumber,
        string $accountName,
    ): Result {
        $owner = PayoutOwnerType::tryFrom($ownerType);

        if ($owner === null) {
            return $this->invalidPayoutAccount();
        }

        $bank = $this->bankLookup->banks([$bankId])[$bankId] ?? null;

        if ($bank === null || ! $bank->isActive) {
            return $this->invalidPayoutAccount();
        }

        try {
            $account = $this->upsertPrimaryAccount($owner, $ownerId, $bankId, $accountNumber, $accountName);
        } catch (UniqueConstraintViolationException) {
            $account = $this->upsertPrimaryAccount($owner, $ownerId, $bankId, $accountNumber, $accountName);
        }

        return Result::ok(new PayoutAccountData(
            id: $account->id,
            ownerType: $account->owner_type->value,
            ownerId: $account->owner_id,
            bankId: $account->bank_id,
            accountNumber: $account->account_number,
            accountName: $account->account_name,
            isPrimary: $account->is_primary,
            status: $account->status->value,
            rejectionReason: $account->rejection_reason,
            bankName: $bank->name,
        ));
    }

    private function upsertPrimaryAccount(
        PayoutOwnerType $owner,
        string $ownerId,
        int $bankId,
        string $accountNumber,
        string $accountName,
    ): PayoutAccount {
        $account = PayoutAccount::query()
            ->forOwner($owner, $ownerId)
            ->primary()
            ->first();

        if ($account === null) {
            $account = new PayoutAccount([
                'owner_type' => $owner->value,
                'owner_id' => $ownerId,
                'is_primary' => true,
                'status' => PayoutStatus::Pending,
            ]);
        }

        $account->fill([
            'bank_id' => $bankId,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
        ]);

        $account->save();

        return $account;
    }

    private function invalidPayoutAccount(): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_payout_account',
            message: 'The selected bank is invalid or inactive.',
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }
}
