<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalReviewStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalReview;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantApprovalReview>
 */
class MerchantApprovalReviewFactory extends Factory
{
    protected $model = MerchantApprovalReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_id' => MerchantApproval::factory(),
            'component' => MerchantApprovalComponent::Identity,
            'subject_type' => MerchantApprovalSubjectType::MerchantIdentity,
            'subject_id' => (string) Str::uuid(),
            'status' => MerchantApprovalReviewStatus::Pending,
            'note' => null,
            'verified_by' => null,
            'verified_at' => null,
            'snapshot_id' => null,
            'content_hash' => null,
        ];
    }

    public function verified(?string $verifiedBy = null): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantApprovalReviewStatus::Verified,
            'verified_by' => $verifiedBy ?? (string) Str::uuid(),
            'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantApprovalReviewStatus::Rejected,
            'verified_by' => (string) Str::uuid(),
            'verified_at' => now(),
        ]);
    }

    public function forSubject(
        MerchantApprovalComponent $component,
        MerchantApprovalSubjectType $subjectType,
        string $subjectId,
    ): static {
        return $this->state(fn (): array => [
            'component' => $component,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }
}
