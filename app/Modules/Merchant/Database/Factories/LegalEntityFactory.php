<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\LegalEntityType;
use App\Modules\Merchant\Domain\Models\LegalEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalEntity>
 */
class LegalEntityFactory extends Factory
{
    protected $model = LegalEntity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => fake()->randomElement(LegalEntityType::cases()),
            'name' => 'PT '.fake()->unique()->company(),
            'nib' => fake()->unique()->numerify('################'),
            'npwp' => fake()->unique()->numerify('##.###.###.#-###.###'),
            'address' => fake()->address(),
            'province_id' => fake()->numberBetween(1, 34),
            'regency_id' => fake()->numberBetween(1, 500),
            'district_id' => fake()->numberBetween(1, 7000),
            'village_id' => fake()->numberBetween(1, 80000),
            'postal_code' => fake()->numerify('#####'),
        ];
    }
}
