<?php

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Operations\Services\ServiceAreaValidator;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use Mockery\MockInterface;

function serviceAreaOutlet(): MerchantOutlet
{
    $outlet = new MerchantOutlet;
    $outlet->province_id = 61;
    $outlet->regency_id = 6106;
    $outlet->district_id = 610601;
    $outlet->village_id = 6106012001;

    return $outlet;
}

function serviceAreaValidator(bool $regionExists = true): ServiceAreaValidator
{
    return new ServiceAreaValidator(test()->mock(GeographyLookup::class, function (MockInterface $mock) use ($regionExists): void {
        $mock->shouldReceive('regionExists')->andReturn($regionExists);
    }));
}

it('accepts a radius service area', function () {
    $result = serviceAreaValidator()->validate(serviceAreaOutlet(), ['type' => 'radius', 'radius_km' => 5]);

    expect($result->isOk())->toBeTrue()
        ->and($result->unwrap())->toBe(['type' => 'radius', 'radius_km' => 5.0]);
});

it('rejects a radius without a positive radius_km', function (array $data) {
    $result = serviceAreaValidator()->validate(serviceAreaOutlet(), $data);

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('invalid_service_area');
})->with([
    'missing radius' => [['type' => 'radius']],
    'zero radius' => [['type' => 'radius', 'radius_km' => 0]],
    'region field present' => [['type' => 'radius', 'radius_km' => 5, 'province_id' => 61]],
]);

it('accepts a region service area matching the outlet region', function () {
    $result = serviceAreaValidator()->validate(serviceAreaOutlet(), [
        'type' => 'province',
        'province_id' => 61,
    ]);

    expect($result->isOk())->toBeTrue()
        ->and($result->unwrap())->toBe(['type' => 'province', 'radius_km' => null]);
});

it('rejects a region service area that does not match the outlet region', function () {
    $result = serviceAreaValidator()->validate(serviceAreaOutlet(), [
        'type' => 'regency',
        'regency_id' => 9999,
    ]);

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('invalid_service_area');
});

it('rejects a region service area when the region is inactive', function () {
    $result = serviceAreaValidator(regionExists: false)->validate(serviceAreaOutlet(), [
        'type' => 'village',
        'village_id' => 6106012001,
    ]);

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('invalid_service_area');
});

it('rejects irrelevant fields for a region service area', function () {
    $result = serviceAreaValidator()->validate(serviceAreaOutlet(), [
        'type' => 'district',
        'district_id' => 610601,
        'radius_km' => 5,
    ]);

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('invalid_service_area');
});
