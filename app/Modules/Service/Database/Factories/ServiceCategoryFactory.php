<?php

namespace App\Modules\Service\Database\Factories;

use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['utensils', 'cup-soda', 'cookie', 'shopping-basket']),
            'is_active' => true,
        ];
    }
}
