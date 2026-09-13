<?php

namespace App\Modules\Geography\Contracts;

use App\Modules\Geography\Contracts\DataTransferObjects\AddressLabelData;

interface GeographyLookup
{
    public function villageExists(int $villageId): bool;

    public function addressLabel(int $villageId): AddressLabelData;

    /**
     * @param  list<int>  $villageIds
     * @return array<int, AddressLabelData> keyed by village id
     */
    public function villageLabels(array $villageIds): array;
}
