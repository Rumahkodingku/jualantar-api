<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantIdentityType;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantIdentity>
 */
class MerchantIdentityFactory extends Factory
{
    protected $model = MerchantIdentity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'id_type' => MerchantIdentityType::Ktp,
            'id_number' => fake()->unique()->numerify('################'),
            'full_name' => fake()->name(),
            'birth_date' => fake()->date(),
        ];
    }
}
