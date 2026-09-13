<?php

use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Domain\Models\Regency;
use App\Modules\Geography\Domain\Models\Village;

beforeEach(function () {
    $this->seedRbac();
});

it('requires the district_id filter', function () {
    $this->getJson('/api/v1/villages')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.district_id.0', 'The district id field is required.');
});

it('lists active villages for a district inside the paginated envelope', function () {
    $district = District::factory()->create();
    Village::factory()->count(2)->create(['district_id' => $district->id, 'is_active' => true]);
    Village::factory()->create(['district_id' => $district->id, 'is_active' => false]);

    $this->getJson("/api/v1/villages?district_id={$district->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure([
            'data' => [['id', 'district_id', 'code', 'name', 'type', 'is_active', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

it('hides villages when any ancestor is inactive', function () {
    $province = Province::factory()->create();
    $regency = Regency::factory()->create(['province_id' => $province->id]);
    $district = District::factory()->create(['regency_id' => $regency->id]);
    Village::factory()->create(['district_id' => $district->id, 'is_active' => true]);

    $district->update(['is_active' => false]);

    $this->getJson("/api/v1/villages?district_id={$district->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $district->update(['is_active' => true]);
    $regency->update(['is_active' => false]);

    $this->getJson("/api/v1/villages?district_id={$district->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $regency->update(['is_active' => true]);
    $province->update(['is_active' => false]);

    $this->getJson("/api/v1/villages?district_id={$district->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $province->update(['is_active' => true]);

    $this->getJson("/api/v1/villages?district_id={$district->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('lists villages deactivated at their own level via the is_active filter', function () {
    $district = District::factory()->create();
    Village::factory()->create(['district_id' => $district->id, 'name' => 'Nonaktif', 'is_active' => false]);

    $this->getJson("/api/v1/villages?district_id={$district->id}&is_active=false")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Nonaktif');
});

it('returns 401 when a guest toggles a village', function () {
    $village = Village::factory()->create();

    $this->patchJson("/api/v1/villages/{$village->id}", ['is_active' => false])
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('lets a super-admin deactivate a village and reactivate it', function () {
    $this->actingAsSuperAdmin();

    $village = Village::factory()->create(['is_active' => true]);

    $this->patchJson("/api/v1/villages/{$village->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->patchJson("/api/v1/villages/{$village->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('deactivates a village via delete idempotently', function () {
    $this->actingAsSuperAdmin();

    $village = Village::factory()->create(['is_active' => true]);

    $this->deleteJson("/api/v1/villages/{$village->id}")->assertNoContent();
    $this->deleteJson("/api/v1/villages/{$village->id}")->assertNoContent();

    $this->assertDatabaseHas('villages', ['id' => $village->id, 'is_active' => false]);
});
