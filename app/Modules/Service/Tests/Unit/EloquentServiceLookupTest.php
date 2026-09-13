<?php

use App\Modules\Service\Contracts\DataTransferObjects\ServiceCategoryData;
use App\Modules\Service\Contracts\DataTransferObjects\ServiceData;
use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use App\Modules\Service\Infrastructure\Repositories\EloquentServiceLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns only active services ordered by name', function () {
    Service::factory()->create(['name' => 'B Service', 'is_active' => true]);
    Service::factory()->create(['name' => 'A Service', 'is_active' => true]);
    Service::factory()->create(['name' => 'C Service', 'is_active' => false]);

    $services = app(EloquentServiceLookup::class)->activeServices();

    expect($services)->toHaveCount(2)
        ->and($services[0])->toBeInstanceOf(ServiceData::class)
        ->and($services[0]->name)->toBe('A Service');
});

it('returns only active categories for a service', function () {
    $service = Service::factory()->create();
    ServiceCategory::factory()->create(['service_id' => $service->id, 'is_active' => true]);
    ServiceCategory::factory()->create(['service_id' => $service->id, 'is_active' => false]);

    $categories = app(EloquentServiceLookup::class)->activeCategoriesForService($service->id);

    expect($categories)->toHaveCount(1)
        ->and($categories[0])->toBeInstanceOf(ServiceCategoryData::class)
        ->and($categories[0]->serviceId)->toBe($service->id);
});

it('checks service existence and category ownership', function () {
    $service = Service::factory()->create();
    $other = Service::factory()->create();
    $category = ServiceCategory::factory()->create(['service_id' => $service->id]);

    $lookup = app(EloquentServiceLookup::class);

    expect($lookup->serviceExists($service->id))->toBeTrue()
        ->and($lookup->serviceExists((string) Str::uuid()))->toBeFalse()
        ->and($lookup->categoryBelongsToService($category->id, $service->id))->toBeTrue()
        ->and($lookup->categoryBelongsToService($category->id, $other->id))->toBeFalse();
});
