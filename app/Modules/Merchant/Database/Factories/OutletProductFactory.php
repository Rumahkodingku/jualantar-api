<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutletProduct>
 */
class OutletProductFactory extends Factory
{
    protected $model = OutletProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'outlet_id' => MerchantOutlet::factory(),
            'product_id' => Product::factory(),
            'status' => CatalogStatus::Active,
            'availability_status' => ProductAvailabilityStatus::Available,
            'unavailable_reason' => null,
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

    public function unavailable(string $reason = 'Out of stock'): static
    {
        return $this->state(fn (): array => [
            'availability_status' => ProductAvailabilityStatus::Unavailable,
            'unavailable_reason' => $reason,
        ]);
    }

    public function forOutlet(MerchantOutlet $outlet): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $outlet->merchant_id,
            'outlet_id' => $outlet->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $product->merchant_id,
            'product_id' => $product->id,
        ]);
    }
}
