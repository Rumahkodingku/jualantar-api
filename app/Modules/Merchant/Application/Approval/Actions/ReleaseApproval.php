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

final class ReleaseApproval
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

            if ($locked->assigned_to !== $actorId) {
                return $this->notAssigned();
            }

            if ($application->status !== MerchantApplicationStatus::InReview) {
                return $this->invalidStateTransition('Only in-review applications can be released.');
            }

            $locked->update([
                'assigned_to' => null,
                'assigned_at' => null,
            ]);

            $application->update(['status' => MerchantApplicationStatus::Pending]);

            $this->recordEvent($locked, MerchantApprovalEventType::ApprovalReleased, $actorId, [
                'application_id' => $application->id,
            ]);

            return Result::ok($locked->refresh());
        });
    }
}
