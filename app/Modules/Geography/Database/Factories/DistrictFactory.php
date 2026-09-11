<?php

namespace App\Modules\Geography\Database\Factories;

use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Domain\Models\Regency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'regency_id' => Regency::factory(),
            'code' => fake()->unique()->numerify('##.##.##'),
            'name' => 'KECAMATAN '.fake()->unique()->citySuffix(),
            'is_active' => true,
        ];
    }
}
