<?php

use App\Modules\Geography\Domain\Models\Province;
use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lists only active provinces in the data envelope', function () {
    Province::factory()->count(2)->create(['is_active' => true]);
    Province::factory()->create(['is_active' => false]);

    $this->getJson('/api/v1/provinces')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonStructure([
            'data' => [['id', 'code', 'name', 'is_active', 'created_at', 'updated_at']],
        ]);
});

it('lists inactive provinces when the is_active filter is false', function () {
    Province::factory()->create(['name' => 'Aktif', 'is_active' => true]);
    Province::factory()->create(['name' => 'Nonaktif', 'is_active' => false]);

    $this->getJson('/api/v1/provinces?is_active=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Nonaktif')
        ->assertJsonPath('data.0.is_active', false);
});

it('searches provinces by name or code', function () {
    Province::factory()->create(['code' => '31', 'name' => 'DKI JAKARTA']);
    Province::factory()->create(['code' => '32', 'name' => 'JAWA BARAT']);

    $this->getJson('/api/v1/provinces?search=jakarta')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', '31');

    $this->getJson('/api/v1/provinces?search=32')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'JAWA BARAT');
});

it('returns a validation problem for invalid index query params', function () {
    $this->getJson('/api/v1/provinces?is_active=maybe')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('shows a single province including inactive ones', function () {
    $active = Province::factory()->create(['is_active' => true]);
    $inactive = Province::factory()->create(['is_active' => false]);

    $this->getJson("/api/v1/provinces/{$active->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $active->id)
        ->assertJsonPath('data.is_active', true);

    $this->getJson("/api/v1/provinces/{$inactive->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('returns a 404 problem when the province does not exist', function () {
    $this->getJson('/api/v1/provinces/999999')
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('returns 401 when a guest toggles a province', function () {
    $province = Province::factory()->create();

    $this->patchJson("/api/v1/provinces/{$province->id}", ['is_active' => false])
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns 403 when the user lacks the geography.update permission', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $province = Province::factory()->create();

    $this->patchJson("/api/v1/provinces/{$province->id}", ['is_active' => false])
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('lets a super-admin deactivate and reactivate a province', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $province = Province::factory()->create(['is_active' => true]);

    $this->patchJson("/api/v1/provinces/{$province->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('provinces', ['id' => $province->id, 'is_active' => false]);

    $this->patchJson("/api/v1/provinces/{$province->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('returns a validation problem when is_active is missing on update', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $province = Province::factory()->create();

    $this->patchJson("/api/v1/provinces/{$province->id}", [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('deactivates a province instead of deleting it and stays idempotent', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $province = Province::factory()->create(['is_active' => true]);

    $this->deleteJson("/api/v1/provinces/{$province->id}")->assertNoContent();
    $this->assertDatabaseHas('provinces', ['id' => $province->id, 'is_active' => false]);

    $this->deleteJson("/api/v1/provinces/{$province->id}")->assertNoContent();
    $this->assertDatabaseHas('provinces', ['id' => $province->id, 'is_active' => false]);
});
