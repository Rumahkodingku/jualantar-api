<?php

use App\Modules\Merchant\Application\Operations\Services\OperationalAvailabilityResolver;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>|null  $operatingHours
 */
function availabilityOutlet(
    MerchantStatus $merchantStatus,
    OutletStatus $outletStatus,
    ?array $operatingHours,
): MerchantOutlet {
    $merchant = new Merchant;
    $merchant->status = $merchantStatus;

    $outlet = new MerchantOutlet;
    $outlet->status = $outletStatus;
    $outlet->operating_hours = $operatingHours;
    $outlet->setRelation('merchant', $merchant);

    return $outlet;
}

beforeEach(function () {
    $this->resolver = new OperationalAvailabilityResolver;
});

it('is open within operating hours when merchant and outlet are active', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-05 12:00:00', 'Asia/Jakarta'));

    $result = $this->resolver->resolve(availabilityOutlet(
        MerchantStatus::Active,
        OutletStatus::Active,
        ['monday' => ['is_open' => true, 'open' => '08:00', 'close' => '22:00']],
    ));

    expect($result['status'])->toBe('open')
        ->and($result['reason'])->toBeNull()
        ->and($result['schedule'])->toBe(['open' => '08:00', 'close' => '22:00']);
});

it('is closed outside operating hours', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-05 23:00:00', 'Asia/Jakarta'));

    $result = $this->resolver->resolve(availabilityOutlet(
        MerchantStatus::Active,
        OutletStatus::Active,
        ['monday' => ['is_open' => true, 'open' => '08:00', 'close' => '22:00']],
    ));

    expect($result['status'])->toBe('closed')
        ->and($result['reason'])->toBe('outside_operating_hours');
});

it('is closed on a scheduled closed day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-05 12:00:00', 'Asia/Jakarta'));

    $result = $this->resolver->resolve(availabilityOutlet(
        MerchantStatus::Active,
        OutletStatus::Active,
        ['monday' => ['is_open' => false]],
    ));

    expect($result['reason'])->toBe('scheduled_closed');
});

it('is closed when merchant or outlet is inactive', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-05 12:00:00', 'Asia/Jakarta'));
    $schedule = ['monday' => ['is_open' => true, 'open' => '08:00', 'close' => '22:00']];

    expect($this->resolver->resolve(availabilityOutlet(MerchantStatus::Inactive, OutletStatus::Active, $schedule))['reason'])
        ->toBe('merchant_inactive')
        ->and($this->resolver->resolve(availabilityOutlet(MerchantStatus::Suspended, OutletStatus::Active, $schedule))['reason'])
        ->toBe('merchant_suspended')
        ->and($this->resolver->resolve(availabilityOutlet(MerchantStatus::Active, OutletStatus::Inactive, $schedule))['reason'])
        ->toBe('outlet_inactive');
});
