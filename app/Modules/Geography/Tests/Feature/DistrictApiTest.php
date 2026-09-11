<?php

use App\Modules\Geography\Domain\Models\District;
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

it('requires the regency_id filter', function () {
    $this->getJson('/api/v1/districts')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.regency_id.0', 'The regency id field is required.');
});

it('lists active districts for a regency inside the paginated envelope', function () {
    $regency = Regency::factory()->create();
    District::factory()->count(2)->create(['regency_id' => $regency->id, 'is_active' => true]);
    District::factory()->create(['regency_id' => $regency->id, 'is_active' => false]);

    $this->getJson("/api/v1/districts?regency_id={$regency->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure([
            'data' => [['id', 'regency_id', 'code', 'name', 'is_active', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

it('hides districts whose regency or province is inactive', function () {
    $province = Province::factory()->create(['is_active' => true]);
    $regency = Regency::factory()->create(['province_id' => $province->id, 'is_active' => true]);
    District::factory()->create(['regency_id' => $regency->id, 'name' => 'Aktif', 'is_active' => true]);
    District::factory()->create(['regency_id' => $regency->id, 'name' => 'Regency nonaktif', 'is_active' => true]);

    $regency->update(['is_active' => false]);

    $this->getJson("/api/v1/districts?regency_id={$regency->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $regency->update(['is_active' => true]);
    $province->update(['is_active' => false]);

    $this->getJson("/api/v1/districts?regency_id={$regency->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $province->update(['is_active' => true]);

    $this->getJson("/api/v1/districts?regency_id={$regency->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lists districts deactivated at their own level via the is_active filter', function () {
    $regency = Regency::factory()->create();
    District::factory()->create(['regency_id' => $regency->id, 'name' => 'Nonaktif', 'is_active' => false]);

    $this->getJson("/api/v1/districts?regency_id={$regency->id}&is_active=false")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Nonaktif');
});

it('searches districts by name or code', function () {
    $regency = Regency::factory()->create();
    District::factory()->create(['regency_id' => $regency->id, 'code' => '31.01.01', 'name' => 'MENTENG']);
    District::factory()->create(['regency_id' => $regency->id, 'code' => '31.01.02', 'name' => 'TANAH ABANG']);

    $this->getJson("/api/v1/districts?regency_id={$regency->id}&search=menteng")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', '31.01.01');
});

it('returns 401 when a guest toggles a district', function () {
    $district = District::factory()->create();

    $this->patchJson("/api/v1/districts/{$district->id}", ['is_active' => false])
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('lets a super-admin deactivate a district and reactivate it', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $district = District::factory()->create(['is_active' => true]);

    $this->patchJson("/api/v1/districts/{$district->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->patchJson("/api/v1/districts/{$district->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('deactivates a district via delete idempotently', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $district = District::factory()->create(['is_active' => true]);

    $this->deleteJson("/api/v1/districts/{$district->id}")->assertNoContent();
    $this->deleteJson("/api/v1/districts/{$district->id}")->assertNoContent();

    $this->assertDatabaseHas('districts', ['id' => $district->id, 'is_active' => false]);
});
