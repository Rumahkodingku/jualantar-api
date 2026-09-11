<?php

namespace App\Modules\Geography\Database\Factories;

use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Domain\Models\Regency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Regency>
 */
class RegencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            'code' => fake()->unique()->numerify('##.##'),
            'name' => 'KABUPATEN '.fake()->unique()->city(),
            'type' => fake()->randomElement(['regency', 'city']),
            'is_active' => true,
        ];
    }
}
