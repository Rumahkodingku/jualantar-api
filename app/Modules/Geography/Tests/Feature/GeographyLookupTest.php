<?php

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Geography\Domain\Exceptions\VillageNotFoundException;
use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Domain\Models\Regency;
use App\Modules\Geography\Domain\Models\Village;

function geographyChain(): array
{
    $province = Province::factory()->create(['name' => 'PROVINSI']);
    $regency = Regency::factory()->create(['province_id' => $province->id, 'name' => 'KABUPATEN']);
    $district = District::factory()->create(['regency_id' => $regency->id, 'name' => 'KECAMATAN']);
    $village = Village::factory()->create(['district_id' => $district->id, 'name' => 'DESA']);

    return compact('province', 'regency', 'district', 'village');
}

it('reports a village as existing only when it and all ancestors are active', function () {
    $lookup = app(GeographyLookup::class);
    ['province' => $province, 'regency' => $regency, 'district' => $district, 'village' => $village] = geographyChain();

    expect($lookup->villageExists($village->id))->toBeTrue();

    $village->update(['is_active' => false]);
    expect($lookup->villageExists($village->id))->toBeFalse();

    $village->update(['is_active' => true]);
    $district->update(['is_active' => false]);
    expect($lookup->villageExists($village->id))->toBeFalse();

    $district->update(['is_active' => true]);
    $regency->update(['is_active' => false]);
    expect($lookup->villageExists($village->id))->toBeFalse();

    $regency->update(['is_active' => true]);
    $province->update(['is_active' => false]);
    expect($lookup->villageExists($village->id))->toBeFalse();
});

it('returns false for a village that does not exist', function () {
    expect(app(GeographyLookup::class)->villageExists(999999))->toBeFalse();
});

it('reports a region as existing per level only when active', function () {
    $lookup = app(GeographyLookup::class);
    ['province' => $province, 'regency' => $regency, 'district' => $district, 'village' => $village] = geographyChain();

    expect($lookup->regionExists('province', $province->id))->toBeTrue()
        ->and($lookup->regionExists('regency', $regency->id))->toBeTrue()
        ->and($lookup->regionExists('district', $district->id))->toBeTrue()
        ->and($lookup->regionExists('village', $village->id))->toBeTrue();

    $province->update(['is_active' => false]);

    expect($lookup->regionExists('province', $province->id))->toBeFalse()
        ->and($lookup->regionExists('regency', $regency->id))->toBeFalse()
        ->and($lookup->regionExists('district', $district->id))->toBeFalse()
        ->and($lookup->regionExists('village', $village->id))->toBeFalse();
});

it('returns false for unknown levels and missing regions', function () {
    expect(app(GeographyLookup::class)->regionExists('galaxy', 1))->toBeFalse()
        ->and(app(GeographyLookup::class)->regionExists('province', 999999))->toBeFalse();
});

it('resolves an address label even when the region is inactive', function () {
    $lookup = app(GeographyLookup::class);
    ['province' => $province, 'regency' => $regency, 'district' => $district, 'village' => $village] = geographyChain();

    $province->update(['is_active' => false]);

    $label = $lookup->addressLabel($village->id);

    expect($label->villageId)->toBe($village->id)
        ->and($label->village)->toBe('DESA')
        ->and($label->district)->toBe('KECAMATAN')
        ->and($label->regency)->toBe('KABUPATEN')
        ->and($label->province)->toBe('PROVINSI');
});

it('throws when resolving an address label for a missing village', function () {
    app(GeographyLookup::class)->addressLabel(999999);
})->throws(VillageNotFoundException::class);
