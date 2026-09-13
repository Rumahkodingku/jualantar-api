<?php

namespace App\Modules\Service\Contracts\DataTransferObjects;

final readonly class ServiceData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $description,
        public string $icon,
    ) {}
}
