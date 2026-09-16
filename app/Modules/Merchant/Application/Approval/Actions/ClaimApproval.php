<?php

namespace App\Modules\Merchant\Application\Approval\Actions;

use App\Modules\Merchant\Application\Common\Concerns\RecordsApprovalEvents;
use App\Modules\Merchant\Application\Common\Concerns\ReportsApprovalErrors;
use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ClaimApproval
{
    use RecordsApprovalEvents, ReportsApprovalErrors;

    public function __invoke(MerchantApproval $approval, string $actorId): Result
    {
        return DB::transaction(function () use ($approval, $actorId): Result {
            $locked = MerchantApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $application = MerchantApplication::query()
                ->whereKey($locked->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== MerchantApplicationStatus::Pending) {
                return $this->invalidStateTransition('Only pending applications can be claimed.');
            }

            if ($locked->assigned_to !== null) {
                return $this->invalidStateTransition('The application has already been claimed.');
            }

            $locked->update([
                'assigned_to' => $actorId,
                'assigned_at' => now(),
                'started_at' => now(),
            ]);

            $application->update(['status' => MerchantApplicationStatus::InReview]);

            $this->recordEvent($locked, MerchantApprovalEventType::ApprovalClaimed, $actorId, [
                'application_id' => $application->id,
            ]);

            return Result::ok($locked->refresh());
        });
    }
}
