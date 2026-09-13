<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\MerchantType;
use App\Modules\Merchant\Domain\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $businessName = Str::title(fake()->unique()->company());

        return [
            'user_id' => (string) Str::uuid(),
            'legal_entity_id' => null,
            'service_id' => (string) Str::uuid(),
            'business_name' => $businessName,
            'slug' => Str::slug($businessName).'-'.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(),
            'type' => MerchantType::Individual,
            'logo' => null,
            'status' => MerchantStatus::Draft,
            'rejection_stage' => null,
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (): array => ['type' => MerchantType::Company]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => MerchantStatus::Pending]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantStatus::Active,
            'reviewed_at' => now(),
            'reviewed_by' => (string) Str::uuid(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => MerchantStatus::Suspended]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchantStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
            'reviewed_at' => now(),
            'reviewed_by' => (string) Str::uuid(),
        ]);
    }

    public function forUser(string $userId): static
    {
        return $this->state(fn (): array => ['user_id' => $userId]);
    }

    public function forService(string $serviceId): static
    {
        return $this->state(fn (): array => ['service_id' => $serviceId]);
    }

    /**
     * An empty registration draft as created before the wizard is filled in.
     */
    public function blankDraft(string $userId): static
    {
        return $this->state(fn (): array => [
            'user_id' => $userId,
            'service_id' => null,
            'business_name' => null,
            'slug' => null,
            'description' => null,
            'type' => null,
            'status' => MerchantStatus::Draft,
        ]);
    }
}
