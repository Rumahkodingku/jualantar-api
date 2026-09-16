<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalRevisionStatus;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantApprovalRevision>
 */
class MerchantApprovalRevisionFactory extends Factory
{
    protected $model = MerchantApprovalRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_id' => MerchantApproval::factory(),
            'requested_by' => (string) Str::uuid(),
            'note' => fake()->sentence(),
            'status' => MerchantApprovalRevisionStatus::Open,
            'requested_at' => now(),
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantApprovalRevisionStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
