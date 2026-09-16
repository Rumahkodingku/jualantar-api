<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalDecision;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantApproval>
 */
class MerchantApprovalFactory extends Factory
{
    protected $model = MerchantApproval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => MerchantApplication::factory(),
            'assigned_to' => null,
            'assigned_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'decision' => null,
            'decision_reason' => null,
        ];
    }

    public function forApplication(string $applicationId): static
    {
        return $this->state(fn (): array => ['application_id' => $applicationId]);
    }

    public function assignedTo(string $userId): static
    {
        return $this->state(fn (): array => [
            'assigned_to' => $userId,
            'assigned_at' => now(),
            'started_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'decision' => MerchantApprovalDecision::Approved,
            'completed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'decision' => MerchantApprovalDecision::Rejected,
            'decision_reason' => fake()->sentence(),
            'completed_at' => now(),
        ]);
    }

    public function completedBy(string $userId): static
    {
        return $this->state(fn (): array => ['assigned_to' => $userId]);
    }

    public function withAssignedUser(): static
    {
        return $this->state(fn (): array => ['assigned_to' => (string) Str::uuid()]);
    }
}
