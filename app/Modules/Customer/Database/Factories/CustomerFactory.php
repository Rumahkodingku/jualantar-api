<?php

namespace App\Modules\Customer\Database\Factories;

use App\Modules\Customer\Domain\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => (string) Str::uuid(),
            'username' => fake()->unique()->userName(),
            'full_name' => fake()->name(),
            'date_of_birth' => null,
            'gender' => null,
            'profile_image' => null,
            'bio' => null,
        ];
    }
}
