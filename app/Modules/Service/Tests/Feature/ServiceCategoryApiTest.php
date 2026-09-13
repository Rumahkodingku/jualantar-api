<?php

use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function serviceCategoryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Makanan',
        'description' => 'Kategori makanan',
        'icon' => 'utensils',
    ], $overrides);
}

it('rejects a guest from creating a category', function () {
    $service = Service::factory()->create();

    $this->postJson("/api/v1/services/{$service->id}/categories", serviceCategoryPayload())
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('forbids a user without the manage permission', function () {
    Sanctum::actingAs(User::factory()->customer()->create());

    $service = Service::factory()->create();

    $this->postJson("/api/v1/services/{$service->id}/categories", serviceCategoryPayload())
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('creates a category under a service and generates its slug', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create();

    $this->postJson("/api/v1/services/{$service->id}/categories", serviceCategoryPayload())
        ->assertCreated()
        ->assertJsonPath('data.service_id', $service->id)
        ->assertJsonPath('data.slug', 'makanan')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('categories', ['service_id' => $service->id, 'slug' => 'makanan']);
});

it('allows the same slug across different services', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $food = Service::factory()->create();
    $mart = Service::factory()->create();

    $this->postJson("/api/v1/services/{$food->id}/categories", serviceCategoryPayload(['name' => 'Minuman']))
        ->assertCreated()
        ->assertJsonPath('data.slug', 'minuman');

    $this->postJson("/api/v1/services/{$mart->id}/categories", serviceCategoryPayload(['name' => 'Minuman']))
        ->assertCreated()
        ->assertJsonPath('data.slug', 'minuman');
});

it('appends a numeric suffix for a duplicate slug within the same service', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create();

    $this->postJson("/api/v1/services/{$service->id}/categories", serviceCategoryPayload(['name' => 'Minuman']))
        ->assertCreated();

    $this->postJson("/api/v1/services/{$service->id}/categories", serviceCategoryPayload(['name' => 'Minuman']))
        ->assertCreated()
        ->assertJsonPath('data.slug', 'minuman-2');
});

it('returns a 404 when creating a category for a missing service', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->postJson('/api/v1/services/'.Str::uuid().'/categories', serviceCategoryPayload())
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('returns a validation problem when the category payload is invalid', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create();

    $this->postJson("/api/v1/services/{$service->id}/categories", [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['name', 'description', 'icon']]);
});

it('lists all categories with pagination metadata and service filter for managers', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $food = Service::factory()->create();
    $mart = Service::factory()->create();

    ServiceCategory::factory()->create(['service_id' => $food->id, 'is_active' => true]);
    ServiceCategory::factory()->create(['service_id' => $food->id, 'is_active' => false]);
    ServiceCategory::factory()->create(['service_id' => $mart->id, 'is_active' => true]);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

    $this->getJson("/api/v1/categories?service_id={$food->id}")
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/categories?is_active=false')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('shows inactive categories to managers', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $category = ServiceCategory::factory()->create(['is_active' => false]);

    $this->getJson("/api/v1/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $category->id)
        ->assertJsonPath('data.is_active', false);
});

it('updates a category and regenerates the slug when the name changes', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $category = ServiceCategory::factory()->create(['name' => 'Makanan', 'slug' => 'makanan']);

    $this->patchJson("/api/v1/categories/{$category->id}", ['name' => 'Minuman'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Minuman')
        ->assertJsonPath('data.slug', 'minuman');
});

it('deactivates a category instead of deleting it and stays idempotent', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $category = ServiceCategory::factory()->create(['is_active' => true]);

    $this->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);

    $this->deleteJson("/api/v1/categories/{$category->id}")->assertNoContent();
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_active' => false]);
});

it('reactivates a category through a partial update', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $category = ServiceCategory::factory()->create(['is_active' => false]);

    $this->patchJson("/api/v1/categories/{$category->id}", ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});
