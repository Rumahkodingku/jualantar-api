<?php

namespace App\Modules\Service\Contracts;

use App\Modules\Service\Contracts\DataTransferObjects\ServiceCategoryData;
use App\Modules\Service\Contracts\DataTransferObjects\ServiceData;

interface ServiceLookup
{
    /**
     * @return list<ServiceData>
     */
    public function activeServices(): array;

    /**
     * @return list<ServiceCategoryData>
     */
    public function activeCategoriesForService(string $serviceId): array;

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, ServiceData> keyed by service id
     */
    public function servicesByIds(array $serviceIds): array;

    /**
     * @param  list<string>  $categoryIds
     * @return array<string, ServiceCategoryData> keyed by category id
     */
    public function categoriesByIds(array $categoryIds): array;

    public function serviceExists(string $serviceId): bool;

    public function categoryBelongsToService(string $categoryId, string $serviceId): bool;
}
