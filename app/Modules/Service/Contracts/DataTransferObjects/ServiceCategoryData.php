<?php

namespace App\Modules\Service\Contracts\DataTransferObjects;

final readonly class ServiceCategoryData
{
    public function __construct(
        public string $id,
        public string $serviceId,
        public string $name,
        public string $slug,
        public string $description,
        public string $icon,
        public bool $isActive,
    ) {}
}
