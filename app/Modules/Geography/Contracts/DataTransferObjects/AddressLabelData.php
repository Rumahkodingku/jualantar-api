<?php

namespace App\Modules\Geography\Contracts\DataTransferObjects;

final readonly class AddressLabelData
{
    public function __construct(
        public int $villageId,
        public string $village,
        public string $district,
        public string $regency,
        public string $province,
    ) {}
}
