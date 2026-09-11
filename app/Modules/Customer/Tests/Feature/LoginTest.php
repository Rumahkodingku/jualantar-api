<?php

use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('logs in a verified customer and returns a sanctum token', function () {
    $user = User::factory()->customer()->create([
        'email' => 'customer@example.com',
        'password' => 'StrongPassword123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'customer@example.com',
        'password' => 'StrongPassword123',
    ])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'customer@example.com')
        ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'name', 'email']]]);

    expect($user->tokens()->count())->toBe(1);
});

it('rejects login when the email is not verified', function () {
    User::factory()->unverified()->create([
        'email' => 'customer@example.com',
        'password' => 'StrongPassword123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'customer@example.com',
        'password' => 'StrongPassword123',
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'email_not_verified');
});

it('rejects login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'StrongPassword123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'customer@example.com',
        'password' => 'WrongPassword123',
    ])
        ->assertStatus(401)
        ->assertJsonPath('code', 'invalid_credentials');
});
