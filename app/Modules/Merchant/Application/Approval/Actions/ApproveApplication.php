<?php

namespace App\Modules\Merchant\Application\Approval\Actions;

use App\Modules\Merchant\Application\Common\Concerns\RecordsApprovalEvents;
use App\Modules\Merchant\Application\Common\Concerns\RegistrationRequirements;
use App\Modules\Merchant\Application\Common\Concerns\ReportsApprovalErrors;
use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalDecision;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalReviewStatus;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalReview;
use App\Modules\Merchant\Notifications\MerchantApprovalNotifier;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ApproveApplication
{
    use RecordsApprovalEvents, RegistrationRequirements, ReportsApprovalErrors;

    public function __construct(private readonly MerchantApprovalNotifier $notifier) {}

    public function __invoke(MerchantApproval $approval, string $actorId): Result
    {
        return DB::transaction(function () use ($approval, $actorId): Result {
            $locked = MerchantApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $application = MerchantApplication::query()
                ->whereKey($locked->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== MerchantApplicationStatus::InReview) {
                return $this->invalidStateTransition('Only in-review applications can be approved.');
            }

            if ($locked->assigned_to !== $actorId) {
                return $this->notAssigned();
            }

            $snapshot = MerchantApplicationSnapshot::query()
                ->where('application_id', $application->id)
                ->orderByDesc('version')
                ->first();

            if ($snapshot === null) {
                return $this->applicationNotFound();
            }

            $missing = $this->unverifiedRequiredSubjects($locked, $snapshot->snapshot);

            if ($missing !== []) {
                return $this->businessRuleViolation(
                    'All required subjects must be verified before approval.',
                    ['components' => $missing],
                );
            }

            $application->update(['status' => MerchantApplicationStatus::Approved]);

            $merchant = Merchant::query()
                ->whereKey($application->merchant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $merchant->update(['status' => MerchantStatus::Active]);

            $locked->update([
                'decision' => MerchantApprovalDecision::Approved,
                'completed_at' => now(),
            ]);

            $this->recordEvent($locked, MerchantApprovalEventType::ApplicationApproved, $actorId, [
                'application_id' => $application->id,
            ]);

            $this->notifier->approved($merchant, $application);

            return Result::ok($locked->refresh());
        });
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    private function unverifiedRequiredSubjects(MerchantApproval $approval, array $snapshot): array
    {
        $missing = [];

        foreach ($this->requiredSubjectsByComponent($snapshot) as $component => $subjects) {
            if ($subjects === []) {
                $missing[] = $component;

                continue;
            }

            foreach ($subjects as $subject) {
                $verified = MerchantApprovalReview::query()
                    ->where('approval_id', $approval->id)
                    ->where('component', $component)
                    ->where('subject_type', $subject['subject_type'])
                    ->where('subject_id', $subject['subject_id'])
                    ->where('status', MerchantApprovalReviewStatus::Verified->value)
                    ->exists();

                if (! $verified) {
                    $missing[] = $component.':'.$subject['subject_id'];
                }
            }
        }

        return array_values(array_unique($missing));
    }
}
