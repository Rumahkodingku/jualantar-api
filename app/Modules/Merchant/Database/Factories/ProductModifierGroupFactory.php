<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductModifierGroup>
 */
class ProductModifierGroupFactory extends Factory
{
    protected $model = ProductModifierGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'product_id' => Product::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'selection_type' => ModifierSelectionType::Multiple,
            'min_selection' => 0,
            'max_selection' => 3,
            'is_required' => false,
            'status' => CatalogStatus::Inactive,
            'display_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function single(): static
    {
        return $this->state(fn (): array => [
            'selection_type' => ModifierSelectionType::Single,
            'min_selection' => 0,
            'max_selection' => 1,
            'is_required' => false,
        ]);
    }

    public function multiple(): static
    {
        return $this->state(fn (): array => [
            'selection_type' => ModifierSelectionType::Multiple,
            'min_selection' => 0,
            'max_selection' => 3,
            'is_required' => false,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (): array => [
            'min_selection' => 1,
            'is_required' => true,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => CatalogStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => CatalogStatus::Inactive]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $product->merchant_id,
            'product_id' => $product->id,
        ]);
    }
}
