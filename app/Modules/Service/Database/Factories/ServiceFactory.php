<?php

namespace App\Modules\Service\Database\Factories;

use App\Modules\Service\Domain\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(2, true)),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['utensils', 'shopping-cart', 'car', 'shopping-bag']),
            'is_active' => true,
        ];
    }
}
