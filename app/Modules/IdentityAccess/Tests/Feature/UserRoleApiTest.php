<?php

use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('returns 401 when a guest requests user roles', function () {
    $target = User::factory()->create();

    $this->getJson("/api/v1/users/{$target->id}/roles")
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns 403 when the authenticated user cannot manage users', function () {
    $driver = User::factory()->driver()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($driver);

    $this->getJson("/api/v1/users/{$target->id}/roles")
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');

    $this->postJson("/api/v1/users/{$target->id}/roles", ['roles' => ['customer']])
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('lists the roles of a user for an admin', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->driver()->create();

    Sanctum::actingAs($admin);

    $this->getJson("/api/v1/users/{$target->id}/roles")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'driver');
});

it('assigns a role to a user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/users/{$target->id}/roles", ['roles' => ['driver']])
        ->assertOk()
        ->assertJsonFragment(['driver']);

    expect($target->fresh()->hasRole('driver'))->toBeTrue();
});

it('removes a role from a user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->merchant()->create();

    Sanctum::actingAs($admin);

    $this->deleteJson("/api/v1/users/{$target->id}/roles", ['roles' => ['merchant']])
        ->assertOk();

    expect($target->fresh()->hasRole('merchant'))->toBeFalse();
});

it('returns a validation problem when assigning an unknown role', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/users/{$target->id}/roles", ['roles' => ['ghost']])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_role');
});

it('blocks an admin from granting the super-admin role', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/users/{$target->id}/roles", ['roles' => ['super-admin']])
        ->assertStatus(403)
        ->assertJsonPath('code', 'privilege_escalation');

    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('allows a super-admin to grant the super-admin role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($superAdmin);

    $this->postJson("/api/v1/users/{$target->id}/roles", ['roles' => ['super-admin']])
        ->assertOk();

    expect($target->fresh()->hasRole('super-admin'))->toBeTrue();
});
