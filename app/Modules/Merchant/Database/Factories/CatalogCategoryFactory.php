<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogCategory>
 */
class CatalogCategoryFactory extends Factory
{
    protected $model = CatalogCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'status' => CatalogStatus::Active,
            'display_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => CatalogStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => CatalogStatus::Inactive]);
    }

    public function forMerchant(string $merchantId): static
    {
        return $this->state(fn (): array => ['merchant_id' => $merchantId]);
    }
}
