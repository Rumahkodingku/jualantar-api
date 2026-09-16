<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use App\Modules\Merchant\Domain\Models\MerchantApprovalRevision;
use App\Modules\Merchant\Domain\Models\MerchantApprovalRevisionItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantApprovalRevisionItem>
 */
class MerchantApprovalRevisionItemFactory extends Factory
{
    protected $model = MerchantApprovalRevisionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revision_id' => MerchantApprovalRevision::factory(),
            'component' => MerchantApprovalComponent::Identity,
            'subject_type' => MerchantApprovalSubjectType::MerchantIdentity,
            'subject_id' => (string) Str::uuid(),
            'reason' => fake()->sentence(),
            'resolved_at' => null,
        ];
    }
}
