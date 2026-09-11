<?php

namespace App\Modules\Geography\Infrastructure\Repositories;

use App\Modules\Geography\Contracts\DataTransferObjects\AddressLabelData;
use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Geography\Domain\Exceptions\VillageNotFoundException;
use App\Modules\Geography\Domain\Models\Village;

final class EloquentGeographyLookup implements GeographyLookup
{
    public function villageExists(int $villageId): bool
    {
        return Village::query()->active()->whereKey($villageId)->exists();
    }

    public function addressLabel(int $villageId): AddressLabelData
    {
        $village = Village::query()
            ->with('district.regency.province')
            ->find($villageId);

        if ($village === null) {
            throw new VillageNotFoundException;
        }

        return new AddressLabelData(
            villageId: $village->id,
            village: $village->name,
            district: $village->district->name,
            regency: $village->district->regency->name,
            province: $village->district->regency->province->name,
        );
    }
}
