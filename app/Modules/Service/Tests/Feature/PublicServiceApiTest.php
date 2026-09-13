<?php

use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use Illuminate\Support\Str;

it('lists only active services in the data envelope', function () {
    Service::factory()->count(2)->create(['is_active' => true]);
    Service::factory()->create(['is_active' => false]);

    $this->getJson('/api/v1/services')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'description', 'icon']],
        ]);
});

it('shows an active service with public fields only', function () {
    $service = Service::factory()->create(['is_active' => true]);

    $this->getJson("/api/v1/services/{$service->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $service->id)
        ->assertJsonPath('data.slug', $service->slug)
        ->assertJsonMissingPath('data.is_active');
});

it('returns a 404 problem when the public service is inactive', function () {
    $service = Service::factory()->create(['is_active' => false]);

    $this->getJson("/api/v1/services/{$service->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('returns a 404 problem when the service does not exist', function () {
    $this->getJson('/api/v1/services/'.Str::uuid())
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('lists only active categories of an active service', function () {
    $service = Service::factory()->create(['is_active' => true]);
    ServiceCategory::factory()->count(2)->create(['service_id' => $service->id, 'is_active' => true]);
    ServiceCategory::factory()->create(['service_id' => $service->id, 'is_active' => false]);

    $this->getJson("/api/v1/services/{$service->id}/categories")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonStructure([
            'data' => [['id', 'service_id', 'name', 'slug', 'description', 'icon']],
        ])
        ->assertJsonMissingPath('data.0.is_active');
});

it('hides categories of an inactive service via the public API', function () {
    $service = Service::factory()->create(['is_active' => false]);
    ServiceCategory::factory()->create(['service_id' => $service->id, 'is_active' => true]);

    $this->getJson("/api/v1/services/{$service->id}/categories")
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});
