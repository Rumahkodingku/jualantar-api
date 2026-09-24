<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'product_id' => Product::factory()->variable(),
            'sku' => strtoupper(fake()->unique()->bothify('???-####')),
            'name' => fake()->unique()->word(),
            'price' => fake()->numberBetween(5_000, 100_000),
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

    public function withoutSku(): static
    {
        return $this->state(fn (): array => ['sku' => null]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $product->merchant_id,
            'product_id' => $product->id,
        ]);
    }
}
