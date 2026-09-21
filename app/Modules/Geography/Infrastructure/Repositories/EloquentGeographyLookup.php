<?php

namespace App\Modules\Geography\Infrastructure\Repositories;

use App\Modules\Geography\Contracts\DataTransferObjects\AddressLabelData;
use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Geography\Domain\Exceptions\VillageNotFoundException;
use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Domain\Models\Regency;
use App\Modules\Geography\Domain\Models\Village;

final class EloquentGeographyLookup implements GeographyLookup
{
    public function villageExists(int $villageId): bool
    {
        return Village::query()->active()->whereKey($villageId)->exists();
    }

    public function regionExists(string $level, int $regionId): bool
    {
        return match ($level) {
            'province' => Province::query()->active()->whereKey($regionId)->exists(),
            'regency' => Regency::query()->active()->whereKey($regionId)->exists(),
            'district' => District::query()->active()->whereKey($regionId)->exists(),
            'village' => $this->villageExists($regionId),
            default => false,
        };
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

    /**
     * @param  list<int>  $villageIds
     * @return array<int, AddressLabelData>
     */
    public function villageLabels(array $villageIds): array
    {
        if ($villageIds === []) {
            return [];
        }

        return Village::query()
            ->with('district.regency.province')
            ->whereKey($villageIds)
            ->get()
            ->keyBy('id')
            ->map(fn (Village $village): AddressLabelData => new AddressLabelData(
                villageId: $village->id,
                village: $village->name,
                district: $village->district->name,
                regency: $village->district->regency->name,
                province: $village->district->regency->province->name,
            ))
            ->all();
    }
}
