<?php

namespace App\Modules\IdentityAccess\Database\Factories;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign the `super-admin` role after the user is created.
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('super-admin'));
    }

    /**
     * Assign the `customer` role after the user is created.
     */
    public function customer(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('customer'));
    }

    /**
     * Assign the `merchant` role after the user is created.
     */
    public function merchant(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('merchant'));
    }

    /**
     * Assign the `driver` role after the user is created.
     */
    public function driver(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole('driver'));
    }
}
