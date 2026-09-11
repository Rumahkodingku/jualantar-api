<?php

use App\Modules\IdentityAccess\Contracts\Authorization;
use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows a driver to view and update deliveries', function () {
    $driver = User::factory()->driver()->create();

    expect($driver->hasPermissionTo('deliveries.view'))->toBeTrue()
        ->and($driver->hasPermissionTo('deliveries.update'))->toBeTrue();
});

it('denies a driver administrative permissions', function () {
    $driver = User::factory()->driver()->create();

    expect($driver->hasPermissionTo('roles.create'))->toBeFalse()
        ->and($driver->hasPermissionTo('users.delete'))->toBeFalse();
});

it('resolves authorization through the public contract', function () {
    $driver = User::factory()->driver()->create();
    $authorization = app(Authorization::class);

    expect($authorization->userHasRole($driver->id, 'driver'))->toBeTrue()
        ->and($authorization->userHasRole($driver->id, 'auditor'))->toBeFalse()
        ->and($authorization->userHasPermission($driver->id, 'deliveries.view'))->toBeTrue()
        ->and($authorization->userHasPermission($driver->id, 'users.delete'))->toBeFalse()
        ->and($authorization->userHasRole(999999, 'driver'))->toBeFalse()
        ->and($authorization->userHasPermission(999999, 'deliveries.view'))->toBeFalse();
});

it('grants a super-admin every ability through the gate', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    expect($superAdmin->can('anything.at.all'))->toBeTrue();
});

it('returns 401 for guests, 403 without permission and 200 with permission', function () {
    $this->getJson('/api/v1/roles')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson('/api/v1/roles')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');

    Sanctum::actingAs(User::factory()->superAdmin()->create());
    $this->getJson('/api/v1/roles')->assertOk();
});

it('does not allow a customer to escalate to super-admin', function () {
    $target = User::factory()->create();

    Sanctum::actingAs(User::factory()->customer()->create());

    $this->postJson("/api/v1/users/{$target->id}/roles", ['roles' => ['super-admin']])
        ->assertStatus(403);

    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('exposes the roles and permissions of the current user', function () {
    $admin = User::factory()->superAdmin()->create();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonFragment(['super-admin'])
        ->assertJsonFragment(['users.view'])
        ->assertJsonMissing(['password']);
});
