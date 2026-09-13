<?php

use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Service\Domain\Models\Service;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function serviceManagementPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'JAfood',
        'description' => 'Layanan makanan dan minuman',
        'icon' => 'utensils',
    ], $overrides);
}

it('rejects a guest from creating a service', function () {
    $this->postJson('/api/v1/services', serviceManagementPayload())
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('forbids a user without the manage permission', function () {
    Sanctum::actingAs(User::factory()->customer()->create());

    $this->postJson('/api/v1/services', serviceManagementPayload())
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('creates a service and generates its slug', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->postJson('/api/v1/services', serviceManagementPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'JAfood')
        ->assertJsonPath('data.slug', 'jafood')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('services', ['slug' => 'jafood']);
});

it('appends a numeric suffix when the generated slug already exists', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    Service::factory()->create(['name' => 'JAfood', 'slug' => 'jafood']);

    $this->postJson('/api/v1/services', serviceManagementPayload())
        ->assertCreated()
        ->assertJsonPath('data.slug', 'jafood-2');
});

it('returns a validation problem when the payload is invalid', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->postJson('/api/v1/services', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['name', 'description', 'icon']]);
});

it('lists all services with pagination metadata for managers', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    Service::factory()->create(['is_active' => true]);
    Service::factory()->create(['is_active' => false]);

    $this->getJson('/api/v1/services')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

it('filters the managed service list by status and search', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    Service::factory()->create(['name' => 'JAfood', 'slug' => 'jafood', 'is_active' => true]);
    Service::factory()->create(['name' => 'JAride', 'slug' => 'jaride', 'is_active' => false]);

    $this->getJson('/api/v1/services?is_active=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'jaride');

    $this->getJson('/api/v1/services?search=jafood')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'jafood');
});

it('shows inactive services to managers', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create(['is_active' => false]);

    $this->getJson("/api/v1/services/{$service->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $service->id)
        ->assertJsonPath('data.is_active', false);
});

it('updates a service and regenerates the slug when the name changes', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create(['name' => 'JAfood', 'slug' => 'jafood']);

    $this->patchJson("/api/v1/services/{$service->id}", ['name' => 'JAmart'])
        ->assertOk()
        ->assertJsonPath('data.name', 'JAmart')
        ->assertJsonPath('data.slug', 'jamart');

    $this->assertDatabaseHas('services', ['id' => $service->id, 'slug' => 'jamart']);
});

it('deactivates a service instead of deleting it and stays idempotent', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create(['is_active' => true]);

    $this->deleteJson("/api/v1/services/{$service->id}")->assertNoContent();
    $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => false]);

    $this->deleteJson("/api/v1/services/{$service->id}")->assertNoContent();
    $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => false]);
});

it('reactivates a service through a partial update', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create(['is_active' => false]);

    $this->patchJson("/api/v1/services/{$service->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});
