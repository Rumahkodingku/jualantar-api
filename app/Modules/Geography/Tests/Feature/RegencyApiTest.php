<?php

use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Domain\Models\Regency;
use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lists only active regencies and can filter by province', function () {
    $province = Province::factory()->create();
    $other = Province::factory()->create();

    Regency::factory()->count(2)->create(['province_id' => $province->id, 'is_active' => true]);
    Regency::factory()->create(['province_id' => $other->id, 'is_active' => true]);
    Regency::factory()->create(['province_id' => $province->id, 'is_active' => false]);

    $this->getJson('/api/v1/regencies')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonMissingPath('meta');

    $this->getJson("/api/v1/regencies?province_id={$province->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'province_id', 'code', 'name', 'type', 'is_active', 'created_at', 'updated_at']],
        ]);
});

it('hides regencies whose province is inactive', function () {
    $province = Province::factory()->create(['is_active' => false]);
    $regency = Regency::factory()->create(['province_id' => $province->id, 'is_active' => true]);

    $this->getJson('/api/v1/regencies')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson("/api/v1/regencies/{$regency->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    $province->update(['is_active' => true]);

    $this->getJson('/api/v1/regencies')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $regency->id);
});

it('lists regencies deactivated at their own level via the is_active filter', function () {
    $province = Province::factory()->create(['is_active' => true]);
    Regency::factory()->create(['province_id' => $province->id, 'name' => 'Aktif', 'is_active' => true]);
    Regency::factory()->create(['province_id' => $province->id, 'name' => 'Nonaktif', 'is_active' => false]);

    $this->getJson('/api/v1/regencies?is_active=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Nonaktif');
});

it('validates the province_id filter', function () {
    $this->getJson('/api/v1/regencies?province_id=999999')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('returns 401 when a guest toggles a regency', function () {
    $regency = Regency::factory()->create();

    $this->patchJson("/api/v1/regencies/{$regency->id}", ['is_active' => false])
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('lets a super-admin deactivate a regency and reactivate it', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $regency = Regency::factory()->create(['is_active' => true]);

    $this->patchJson("/api/v1/regencies/{$regency->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('regencies', ['id' => $regency->id, 'is_active' => false]);

    $this->patchJson("/api/v1/regencies/{$regency->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('deactivates a regency via delete idempotently', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $regency = Regency::factory()->create(['is_active' => true]);

    $this->deleteJson("/api/v1/regencies/{$regency->id}")->assertNoContent();
    $this->deleteJson("/api/v1/regencies/{$regency->id}")->assertNoContent();

    $this->assertDatabaseHas('regencies', ['id' => $regency->id, 'is_active' => false]);
});
