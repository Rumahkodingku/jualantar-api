<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ReopenMerchantRegistration
{
    use ReportsRegistrationErrors;

    /**
     * Move a rejected registration back to draft so the owner can fix and
     * resubmit it. Rejection metadata is cleared on reopen.
     */
    public function __invoke(Merchant $merchant): Result
    {
        if ($merchant->status !== MerchantStatus::Rejected) {
            return $this->invalidRegistrationState('Only rejected registrations can be reopened.');
        }

        DB::transaction(function () use ($merchant): void {
            $locked = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => MerchantStatus::Draft,
                'rejection_stage' => null,
                'rejection_reason' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);
        });

        return Result::ok($merchant->refresh());
    }
}
