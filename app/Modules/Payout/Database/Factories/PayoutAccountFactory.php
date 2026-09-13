<?php

namespace App\Modules\Payout\Database\Factories;

use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Enums\PayoutStatus;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PayoutAccount>
 */
class PayoutAccountFactory extends Factory
{
    protected $model = PayoutAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => PayoutOwnerType::Merchant,
            'owner_id' => (string) Str::uuid(),
            // Cross-module reference to bank_directory.banks: keep it opaque.
            'bank_id' => fake()->numberBetween(1, 9999),
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'is_primary' => true,
            'status' => PayoutStatus::Pending,
            'rejection_reason' => null,
            'verified_at' => null,
            'verified_by' => null,
        ];
    }

    public function forOwner(PayoutOwnerType $ownerType, string $ownerId): static
    {
        return $this->state(fn (): array => [
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::Active,
            'verified_at' => now(),
            'verified_by' => (string) Str::uuid(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function secondary(): static
    {
        return $this->state(fn (): array => ['is_primary' => false]);
    }
}
