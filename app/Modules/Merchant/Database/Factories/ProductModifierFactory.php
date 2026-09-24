<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductModifier>
 */
class ProductModifierFactory extends Factory
{
    protected $model = ProductModifier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'modifier_group_id' => ProductModifierGroup::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(0, 20_000),
            'status' => CatalogStatus::Active,
            'is_default' => false,
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

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function forGroup(ProductModifierGroup $group): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $group->merchant_id,
            'modifier_group_id' => $group->id,
        ]);
    }
}
