<?php

namespace App\Modules\Geography\Contracts;

use App\Modules\Geography\Contracts\DataTransferObjects\AddressLabelData;

interface GeographyLookup
{
    public function villageExists(int $villageId): bool;

    /**
     * Whether a region of the given level exists and is effectively active.
     *
     * Levels are primitives ("province", "regency", "district", "village") so
     * consumers never have to share an enum with this module.
     */
    public function regionExists(string $level, int $regionId): bool;

    public function addressLabel(int $villageId): AddressLabelData;

    /**
     * @param  list<int>  $villageIds
     * @return array<int, AddressLabelData> keyed by village id
     */
    public function villageLabels(array $villageIds): array;
}
