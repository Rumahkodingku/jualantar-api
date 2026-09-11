<?php

use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
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

it('authenticates any role through the same global login endpoint', function (string $state, string $role) {
    User::factory()->{$state}()->create([
        'email' => 'user@example.com',
        'password' => 'StrongPassword123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'StrongPassword123',
    ])
        ->assertOk()
        ->assertJsonFragment([$role]);
})->with([
    'driver' => ['driver', 'driver'],
    'merchant' => ['merchant', 'merchant'],
    'super admin' => ['superAdmin', 'super-admin'],
]);

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

it('rejects login with an unknown email', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'StrongPassword123',
    ])
        ->assertStatus(401)
        ->assertJsonPath('code', 'invalid_credentials');
});

it('rejects login with an invalid password', function () {
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

it('logs out and revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('returns 401 when logging out without a token', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns the authenticated identity through /auth/me', function () {
    $user = User::factory()->superAdmin()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonFragment(['super-admin'])
        ->assertJsonMissing(['password']);
});
