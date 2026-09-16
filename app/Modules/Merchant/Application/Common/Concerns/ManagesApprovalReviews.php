<?php

namespace App\Modules\Merchant\Application\Common\Concerns;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalReviewStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalRevisionStatus;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalReview;
use App\Modules\Merchant\Domain\Models\MerchantApprovalRevision;
use App\Modules\Merchant\Domain\Models\MerchantApprovalRevisionItem;

trait ManagesApprovalReviews
{
    use CanonicalHasher;

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    private function findSubject(array $snapshot, string $subjectType, string $subjectId): ?array
    {
        $entry = $snapshot['subjects'][$subjectType] ?? null;

        if ($entry === null) {
            return null;
        }

        $list = array_is_list($entry) ? $entry : [$entry];

        foreach ($list as $subject) {
            if (($subject['subject_id'] ?? null) === $subjectId) {
                return $subject;
            }
        }

        return null;
    }

    /**
     * Reset the current review state against a freshly created snapshot:
     * changed subjects and previously rejected subjects go back to pending,
     * unchanged verified subjects keep their verification but now reference the
     * latest snapshot, and subjects that no longer exist are dropped.
     */
    private function applyReviewReset(MerchantApproval $approval, MerchantApplicationSnapshot $snapshot): void
    {
        $payload = $snapshot->snapshot;

        $reviews = MerchantApprovalReview::query()
            ->where('approval_id', $approval->id)
            ->get();

        foreach ($reviews as $review) {
            $subject = $this->findSubject($payload, $review->subject_type->value, $review->subject_id);

            if ($subject === null) {
                $review->delete();

                continue;
            }

            $hash = $this->canonicalHash($subject['data'] ?? []);
            $reset = $review->status === MerchantApprovalReviewStatus::Rejected
                || $review->content_hash !== $hash;

            $review->update([
                'status' => $reset ? MerchantApprovalReviewStatus::Pending : $review->status,
                'verified_by' => $reset ? null : $review->verified_by,
                'verified_at' => $reset ? null : $review->verified_at,
                'snapshot_id' => $snapshot->id,
                'content_hash' => $hash,
            ]);
        }
    }

    private function resolveOpenRevisions(MerchantApproval $approval): void
    {
        $revisions = MerchantApprovalRevision::query()
            ->where('approval_id', $approval->id)
            ->where('status', MerchantApprovalRevisionStatus::Open)
            ->get();

        foreach ($revisions as $revision) {
            $revision->update([
                'status' => MerchantApprovalRevisionStatus::Resolved,
                'resolved_at' => now(),
            ]);

            MerchantApprovalRevisionItem::query()
                ->where('revision_id', $revision->id)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);
        }
    }
}
