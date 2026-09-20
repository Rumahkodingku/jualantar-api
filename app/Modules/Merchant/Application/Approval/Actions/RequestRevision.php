<?php

namespace App\Modules\Merchant\Application\Approval\Actions;

use App\Modules\Merchant\Application\Common\Concerns\ManagesApprovalReviews;
use App\Modules\Merchant\Application\Common\Concerns\RecordsApprovalEvents;
use App\Modules\Merchant\Application\Common\Concerns\ReportsApprovalErrors;
use App\Modules\Merchant\Application\Common\MerchantApprovalCommunicator;
use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalRevisionStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalRevision;
use App\Modules\Merchant\Domain\Models\MerchantApprovalRevisionItem;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class RequestRevision
{
    use ManagesApprovalReviews, RecordsApprovalEvents, ReportsApprovalErrors;

    public function __construct(private readonly MerchantApprovalCommunicator $communicator) {}

    /**
     * @param  list<array{component: string, subject_type: string, subject_id: string, reason: string}>  $items
     */
    public function __invoke(MerchantApproval $approval, string $actorId, ?string $note, array $items): Result
    {
        $result = DB::transaction(function () use ($approval, $actorId, $note, $items): Result {
            $locked = MerchantApproval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $application = MerchantApplication::query()
                ->whereKey($locked->application_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== MerchantApplicationStatus::InReview) {
                return $this->invalidStateTransition('Only in-review applications can require revision.');
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

            foreach ($items as $item) {
                if ($this->findSubject($snapshot->snapshot, $item['subject_type'], $item['subject_id']) === null) {
                    return $this->businessRuleViolation(
                        'One or more revision items are not part of the current submission.',
                    );
                }
            }

            $revision = MerchantApprovalRevision::query()->create([
                'approval_id' => $locked->id,
                'requested_by' => $actorId,
                'note' => $note,
                'status' => MerchantApprovalRevisionStatus::Open,
                'requested_at' => now(),
            ]);

            foreach ($items as $item) {
                MerchantApprovalRevisionItem::query()->create([
                    'revision_id' => $revision->id,
                    'component' => $item['component'],
                    'subject_type' => $item['subject_type'],
                    'subject_id' => $item['subject_id'],
                    'reason' => $item['reason'],
                ]);
            }

            $application->update(['status' => MerchantApplicationStatus::RevisionRequired]);

            $this->recordEvent($locked, MerchantApprovalEventType::RevisionRequested, $actorId, [
                'application_id' => $application->id,
                'revision_id' => $revision->id,
                'note' => $note,
                'items' => $items,
            ]);

            return Result::ok($revision->refresh());
        });

        if ($result->isOk()) {
            $this->communicate($approval, $note);
        }

        return $result;
    }

    private function communicate(MerchantApproval $approval, ?string $note): void
    {
        $application = MerchantApplication::query()->find($approval->application_id);
        $merchant = $application?->merchant;

        if ($application !== null && $merchant instanceof Merchant) {
            $this->communicator->revisionRequested($merchant, $application, $note);
        }
    }
}
