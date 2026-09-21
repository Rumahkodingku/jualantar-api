<?php

use App\Modules\Merchant\Application\Operations\Services\OperatingHoursValidator;

it('normalizes a valid operating hours schedule', function () {
    $result = (new OperatingHoursValidator)->validate([
        'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '22:00'],
        'sunday' => ['is_open' => false, 'open' => '08:00', 'close' => '09:00'],
    ]);

    expect($result->isOk())->toBeTrue();

    $hours = $result->unwrap();

    expect($hours['monday'])->toBe(['is_open' => true, 'open' => '08:00', 'close' => '22:00'])
        ->and($hours['sunday'])->toBe(['is_open' => false]);
});

it('rejects an invalid operating hours schedule', function (array $hours) {
    $result = (new OperatingHoursValidator)->validate($hours);

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('invalid_operating_hours')
        ->and($result->error()->status)->toBe(422);
})->with([
    'unknown day' => [['funday' => ['is_open' => true, 'open' => '08:00', 'close' => '09:00']]],
    'overnight' => [['monday' => ['is_open' => true, 'open' => '22:00', 'close' => '08:00']]],
    'equal times' => [['monday' => ['is_open' => true, 'open' => '08:00', 'close' => '08:00']]],
    'missing times' => [['monday' => ['is_open' => true]]],
    'malformed time' => [['monday' => ['is_open' => true, 'open' => '8am', 'close' => '09:00']]],
    'missing is_open' => [['monday' => ['open' => '08:00', 'close' => '09:00']]],
    'non boolean is_open' => [['monday' => ['is_open' => 'yes']]],
]);
