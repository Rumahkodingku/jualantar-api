<?php

namespace App\Modules\Geography\Database\Factories;

use App\Modules\Geography\Domain\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('##'),
            'name' => 'PROVINSI '.fake()->unique()->state(),
            'is_active' => true,
        ];
    }
}
