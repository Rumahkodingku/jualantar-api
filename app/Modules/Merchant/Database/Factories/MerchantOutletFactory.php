<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\OutletServiceAreaType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantOutlet>
 */
class MerchantOutletFactory extends Factory
{
    protected $model = MerchantOutlet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => fake()->company(),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'province_id' => fake()->numberBetween(1, 34),
            'regency_id' => fake()->numberBetween(1, 500),
            'district_id' => fake()->numberBetween(1, 7000),
            'village_id' => fake()->numberBetween(1, 80000),
            'postal_code' => fake()->numerify('#####'),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'service_area_type' => OutletServiceAreaType::Radius,
            'service_radius_km' => fake()->randomFloat(2, 1, 25),
            'operating_hours' => [
                'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '17:00'],
                'tuesday' => ['is_open' => true, 'open' => '08:00', 'close' => '17:00'],
            ],
            'status' => OutletStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => OutletStatus::Inactive]);
    }
}
