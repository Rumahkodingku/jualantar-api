<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantApplication>
 */
class MerchantApplicationFactory extends Factory
{
    protected $model = MerchantApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'application_number' => 'MA-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'status' => MerchantApplicationStatus::Draft,
            'submitted_at' => null,
        ];
    }

    public function forMerchant(string $merchantId): static
    {
        return $this->state(fn (): array => ['merchant_id' => $merchantId]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantApplicationStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantApplicationStatus::InReview,
            'submitted_at' => now(),
        ]);
    }

    public function revisionRequired(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantApplicationStatus::RevisionRequired,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantApplicationStatus::Approved,
            'submitted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantApplicationStatus::Rejected,
            'submitted_at' => now(),
        ]);
    }
}
