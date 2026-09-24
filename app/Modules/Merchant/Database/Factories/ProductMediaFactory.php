<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\ProductMediaMimeType;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    protected $model = ProductMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'product_id' => Product::factory(),
            'storage_key' => 'merchants/'.Str::uuid().'/products/'.Str::uuid().'/'.Str::uuid().'.jpg',
            'mime_type' => ProductMediaMimeType::Jpeg->value,
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            'alt_text' => fake()->sentence(3),
            'is_primary' => false,
            'display_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $product->merchant_id,
            'product_id' => $product->id,
        ]);
    }
}
