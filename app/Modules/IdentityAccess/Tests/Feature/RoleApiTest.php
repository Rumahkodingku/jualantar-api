<?php

use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function rolePayload(array $overrides = []): array
{
    return array_merge(['name' => 'auditor'], $overrides);
}

it('returns 401 when a guest requests the role list', function () {
    $this->getJson('/api/v1/roles')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns 403 when an authenticated user lacks the permission', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $this->getJson('/api/v1/roles')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('lists roles with their permissions for a super-admin', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('meta.total', count(RbacSeeder::ROLE_PERMISSIONS))
        ->assertJsonStructure([
            'data' => [['id', 'name', 'guard_name', 'permissions', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

it('grants a super-admin full access to the role list', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->getJson('/api/v1/roles')->assertOk();
});

it('filters roles by search term', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/roles?search=merchant')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'merchant');
});

it('shows a single role', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $role = Role::findByName('merchant');

    $this->getJson("/api/v1/roles/{$role->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'merchant')
        ->assertJsonFragment(['products.create']);
});

it('creates a role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/v1/roles', rolePayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'auditor');

    $this->assertDatabaseHas('roles', ['name' => 'auditor', 'guard_name' => 'sanctum']);
});

it('returns a validation problem when creating a duplicate role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/v1/roles', rolePayload(['name' => 'driver']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.name.0', 'The name has already been taken.');
});

it('returns a validation problem for an invalid role name', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/v1/roles', rolePayload(['name' => 'Not Valid!']))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('updates a role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $role = Role::create(['name' => 'auditor']);

    $this->putJson("/api/v1/roles/{$role->id}", rolePayload(['name' => 'reviewer']))
        ->assertOk()
        ->assertJsonPath('data.name', 'reviewer');

    $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'reviewer']);
});

it('refuses to rename the protected super-admin role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $role = Role::findByName('super-admin');

    $this->putJson("/api/v1/roles/{$role->id}", rolePayload(['name' => 'root']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'protected_role');
});

it('refuses to delete a role that is still assigned to users', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->driver()->create();

    Sanctum::actingAs($superAdmin);

    $role = Role::findByName('driver');

    $this->deleteJson("/api/v1/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'role_in_use');
});

it('refuses to delete the protected super-admin role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $role = Role::findByName('super-admin');

    $this->deleteJson("/api/v1/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'protected_role');
});

it('deletes an unused role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $role = Role::create(['name' => 'auditor']);

    $this->deleteJson("/api/v1/roles/{$role->id}")->assertNoContent();

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

it('assigns permissions to a role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $role = Role::create(['name' => 'auditor']);

    $this->postJson("/api/v1/roles/{$role->id}/permissions", [
        'permissions' => ['users.view', 'users.create'],
    ])
        ->assertOk()
        ->assertJsonFragment(['users.view'])
        ->assertJsonFragment(['users.create']);
});

it('removes permissions from a role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $role = Role::findByName('merchant');

    $this->deleteJson("/api/v1/roles/{$role->id}/permissions", [
        'permissions' => ['products.create'],
    ])
        ->assertOk();

    expect(Role::findByName('merchant')->hasPermissionTo('products.create'))->toBeFalse();
});

it('returns a validation problem when assigning an unknown permission', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $role = Role::findByName('merchant');

    $this->postJson("/api/v1/roles/{$role->id}/permissions", [
        'permissions' => ['nope.nope'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_permission');
});
