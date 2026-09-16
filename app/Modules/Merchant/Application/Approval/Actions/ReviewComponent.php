<?php

namespace App\Modules\Merchant\Application\Approval\Actions;

use App\Modules\Merchant\Application\Common\Concerns\ManagesApprovalReviews;
use App\Modules\Merchant\Application\Common\Concerns\RecordsApprovalEvents;
use App\Modules\Merchant\Application\Common\Concerns\ReportsApprovalErrors;
use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalReviewStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalReview;
use App\Modules\Payout\Contracts\PayoutAccountVerification;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ReviewComponent
{
    use ManagesApprovalReviews, RecordsApprovalEvents, ReportsApprovalErrors;

    public function __construct(private readonly PayoutAccountVerification $payoutVerification) {}

    public function __invoke(
        MerchantApproval $approval,
        string $actorId,
        MerchantApprovalComponent $component,
        MerchantApprovalSubjectType $subjectType,
        string $subjectId,
        MerchantApprovalReviewStatus $status,
        ?string $note,
    ): Result {
        return DB::transaction(function () use ($approval, $actorId, $component, $subjectType, $subjectId, $status, $note): Result {
            $locked = MerchantApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $application = MerchantApplication::query()
                ->whereKey($locked->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== MerchantApplicationStatus::InReview) {
                return $this->invalidStateTransition('Only in-review applications can be reviewed.');
            }

            if ($locked->assigned_to !== $actorId) {
                return $this->notAssigned();
            }

            if ($component !== $subjectType->component()) {
                return $this->businessRuleViolation('The component does not match the subject type.');
            }

            $snapshot = MerchantApplicationSnapshot::query()
                ->where('application_id', $application->id)
                ->orderByDesc('version')
                ->first();

            if ($snapshot === null) {
                return $this->applicationNotFound();
            }

            $subject = $this->findSubject($snapshot->snapshot, $subjectType->value, $subjectId);

            if ($subject === null) {
                return $this->businessRuleViolation('The subject is not part of the current submission.');
            }

            $review = MerchantApprovalReview::query()->firstOrNew([
                'approval_id' => $locked->id,
                'component' => $component->value,
                'subject_type' => $subjectType->value,
                'subject_id' => $subjectId,
            ]);

            $isNew = ! $review->exists;

            $review->fill([
                'status' => $status,
                'note' => $note,
                'verified_by' => $actorId,
                'verified_at' => now(),
                'snapshot_id' => $snapshot->id,
                'content_hash' => $this->canonicalHash($subject['data'] ?? []),
            ])->save();

            if ($component === MerchantApprovalComponent::Payout) {
                $payoutResult = $status === MerchantApprovalReviewStatus::Verified
                    ? $this->payoutVerification->markVerified($subjectId, $actorId)
                    : $this->payoutVerification->markRejected($subjectId, $note ?? 'Rejected by reviewer.', $actorId);

                if ($payoutResult->isErr()) {
                    return $payoutResult;
                }
            }

            $this->recordEvent($locked, MerchantApprovalEventType::ComponentReviewed, $actorId, [
                'component' => $component->value,
                'subject_type' => $subjectType->value,
                'subject_id' => $subjectId,
                'status' => $status->value,
                'note' => $note,
            ]);

            return Result::ok([
                'review' => $review->refresh(),
                'created' => $isNew,
            ]);
        });
    }
}
