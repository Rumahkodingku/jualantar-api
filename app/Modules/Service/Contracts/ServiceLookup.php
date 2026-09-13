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

    public function serviceExists(string $serviceId): bool;

    public function categoryBelongsToService(string $categoryId, string $serviceId): bool;
}
