<?php

namespace App\Modules\Merchant\Application\Approval\Actions;

use App\Modules\Merchant\Application\Common\Concerns\RecordsApprovalEvents;
use App\Modules\Merchant\Application\Common\Concerns\ReportsApprovalErrors;
use App\Modules\Merchant\Application\Common\MerchantApprovalCommunicator;
use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalDecision;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class RejectApplication
{
    use RecordsApprovalEvents, ReportsApprovalErrors;

    public function __construct(private readonly MerchantApprovalCommunicator $communicator) {}

    public function __invoke(MerchantApproval $approval, string $actorId, string $reason): Result
    {
        $result = DB::transaction(function () use ($approval, $actorId, $reason): Result {
            $locked = MerchantApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $application = MerchantApplication::query()
                ->whereKey($locked->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== MerchantApplicationStatus::InReview) {
                return $this->invalidStateTransition('Only in-review applications can be rejected.');
            }

            if ($locked->assigned_to !== $actorId) {
                return $this->notAssigned();
            }

            $application->update(['status' => MerchantApplicationStatus::Rejected]);

            $locked->update([
                'decision' => MerchantApprovalDecision::Rejected,
                'decision_reason' => $reason,
                'completed_at' => now(),
            ]);

            $this->recordEvent($locked, MerchantApprovalEventType::ApplicationRejected, $actorId, [
                'application_id' => $application->id,
                'reason' => $reason,
            ]);

            return Result::ok($locked->refresh());
        });

        if ($result->isOk()) {
            $this->communicate($approval, $reason);
        }

        return $result;
    }

    private function communicate(MerchantApproval $approval, string $reason): void
    {
        $application = MerchantApplication::query()->find($approval->application_id);
        $merchant = $application?->merchant;

        if ($application !== null && $merchant instanceof Merchant) {
            $this->communicator->rejected($merchant, $application, $reason);
        }
    }
}
