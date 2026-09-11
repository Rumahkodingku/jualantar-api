<?php

namespace App\Modules\Geography\Database\Factories;

use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Domain\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Village>
 */
class VillageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'code' => fake()->unique()->numerify('##.##.##.####'),
            'name' => 'DESA '.fake()->unique()->streetName(),
            'type' => fake()->randomElement(['village', 'urban_village']),
            'is_active' => true,
        ];
    }
}
