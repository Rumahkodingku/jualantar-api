<?php

namespace App\Modules\Payout\Infrastructure\Repositories;

use App\Modules\Payout\Contracts\PayoutAccountVerification;
use App\Modules\Payout\Domain\Enums\PayoutStatus;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

final class EloquentPayoutAccountVerification implements PayoutAccountVerification
{
    public function markVerified(string $payoutAccountId, string $verifiedBy): Result
    {
        $account = PayoutAccount::query()->find($payoutAccountId);

        if ($account === null) {
            return $this->notFound();
        }

        if (! $account->status->canTransitionTo(PayoutStatus::Active)) {
            return $this->invalidState();
        }

        $account->update([
            'status' => PayoutStatus::Active,
            'rejection_reason' => null,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);

        return Result::ok($account->refresh());
    }

    public function markRejected(string $payoutAccountId, string $reason, string $verifiedBy): Result
    {
        $account = PayoutAccount::query()->find($payoutAccountId);

        if ($account === null) {
            return $this->notFound();
        }

        if (! $account->status->canTransitionTo(PayoutStatus::Rejected)) {
            return $this->invalidState();
        }

        $account->update([
            'status' => PayoutStatus::Rejected,
            'rejection_reason' => $reason,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);

        return Result::ok($account->refresh());
    }

    private function notFound(): Result
    {
        return Result::err(new ResultError(
            code: 'not_found',
            message: 'The payout account was not found.',
            status: 404,
            title: 'Not Found',
        ));
    }

    private function invalidState(): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_state_transition',
            message: 'The payout account is not in a state that allows this transition.',
            status: 409,
            title: 'Conflict',
        ));
    }
}
