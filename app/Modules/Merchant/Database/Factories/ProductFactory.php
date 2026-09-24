<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'category_id' => CatalogCategory::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'product_type' => ProductType::Simple,
            'price' => fake()->numberBetween(5_000, 100_000),
            'status' => CatalogStatus::Inactive,
            'display_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function simple(): static
    {
        return $this->state(fn (): array => [
            'product_type' => ProductType::Simple,
            'price' => fake()->numberBetween(5_000, 100_000),
        ]);
    }

    public function variable(): static
    {
        return $this->state(fn (): array => [
            'product_type' => ProductType::Variable,
            'price' => null,
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

    public function forCategory(CatalogCategory $category): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $category->merchant_id,
            'category_id' => $category->id,
        ]);
    }

    public function forMerchant(string $merchantId): static
    {
        return $this->state(fn (): array => ['merchant_id' => $merchantId]);
    }
}
