<?php

use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Modules\IdentityAccess\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('returns 401 when a guest requests the permission list', function () {
    $this->getJson('/api/v1/permissions')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns 403 when an authenticated user lacks the permission', function () {
    $merchant = User::factory()->merchant()->create();
    Sanctum::actingAs($merchant);

    $this->getJson('/api/v1/permissions')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('lists permissions for a super-admin who holds permissions.view', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonPath('meta.total', count(RbacSeeder::PERMISSIONS))
        ->assertJsonStructure([
            'data' => [['id', 'name', 'guard_name', 'roles', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

it('filters permissions by search term', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $expected = count(array_filter(
        RbacSeeder::PERMISSIONS,
        fn (string $permission): bool => str_contains($permission, 'users.'),
    ));

    $this->getJson('/api/v1/permissions?search=users.')
        ->assertOk()
        ->assertJsonCount($expected, 'data');
});

it('shows a single permission', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $permission = Permission::findByName('users.view');

    $this->getJson("/api/v1/permissions/{$permission->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'users.view');
});

it('creates a permission', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/v1/permissions', ['name' => 'reports.view'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'reports.view');

    $this->assertDatabaseHas('permissions', ['name' => 'reports.view', 'guard_name' => 'sanctum']);
});

it('returns a validation problem when creating a duplicate permission', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/v1/permissions', ['name' => 'users.view'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.name.0', 'The name has already been taken.');
});

it('returns a validation problem for a malformed permission name', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/v1/permissions', ['name' => 'manageUsers'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('updates a permission', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $permission = Permission::create(['name' => 'reports.view']);

    $this->putJson("/api/v1/permissions/{$permission->id}", ['name' => 'reports.list'])
        ->assertOk()
        ->assertJsonPath('data.name', 'reports.list');
});

it('refuses to delete a permission that is in use', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $permission = Permission::findByName('users.view');

    $this->deleteJson("/api/v1/permissions/{$permission->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'permission_in_use');
});

it('deletes an unused permission', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($superAdmin);

    $permission = Permission::create(['name' => 'reports.view']);

    $this->deleteJson("/api/v1/permissions/{$permission->id}")->assertNoContent();

    $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
});
