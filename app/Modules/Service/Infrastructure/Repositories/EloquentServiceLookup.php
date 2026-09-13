<?php

namespace App\Modules\Service\Infrastructure\Repositories;

use App\Modules\Service\Contracts\DataTransferObjects\ServiceCategoryData;
use App\Modules\Service\Contracts\DataTransferObjects\ServiceData;
use App\Modules\Service\Contracts\ServiceLookup;
use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;

final class EloquentServiceLookup implements ServiceLookup
{
    public function activeServices(): array
    {
        return Service::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service): ServiceData => new ServiceData(
                id: $service->id,
                name: $service->name,
                slug: $service->slug,
                description: $service->description,
                icon: $service->icon,
            ))
            ->all();
    }

    public function activeCategoriesForService(string $serviceId): array
    {
        return ServiceCategory::query()
            ->active()
            ->where('service_id', $serviceId)
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceCategory $category): ServiceCategoryData => new ServiceCategoryData(
                id: $category->id,
                serviceId: $category->service_id,
                name: $category->name,
                slug: $category->slug,
                description: $category->description,
                icon: $category->icon,
            ))
            ->all();
    }

    public function serviceExists(string $serviceId): bool
    {
        return Service::query()->whereKey($serviceId)->exists();
    }

    public function categoryBelongsToService(string $categoryId, string $serviceId): bool
    {
        return ServiceCategory::query()
            ->whereKey($categoryId)
            ->where('service_id', $serviceId)
            ->exists();
    }
}
