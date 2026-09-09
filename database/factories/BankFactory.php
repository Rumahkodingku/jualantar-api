<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('###'),
            'name' => 'PT BANK '.fake()->unique()->company(),
            'category' => fake()->randomElement(Bank::CATEGORIES),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'website' => 'www.'.fake()->domainName(),
            'is_active' => true,
        ];
    }
}
