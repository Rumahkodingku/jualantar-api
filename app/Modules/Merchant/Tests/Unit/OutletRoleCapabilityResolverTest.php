<?php

use App\Modules\Merchant\Domain\Authorization\OutletRoleCapabilityResolver;
use App\Modules\Merchant\Domain\Enums\OutletUserRole;

it('resolves the full capability list for an outlet manager', function () {
    $resolver = new OutletRoleCapabilityResolver;

    expect($resolver->permissionsFor(OutletUserRole::OutletManager))->toBe([
        'merchant.operations.view',
        'merchant.operations.outlets.view',
        'merchant.operations.outlets.update',
        'merchant.operations.outlets.status.update',
        'merchant.operations.outlet_users.view',
        'merchant.operations.outlet_users.assign',
        'merchant.operations.outlet_users.remove',
        'merchant.operations.outlet_users.role.update',
        'merchant.operations.hours.view',
        'merchant.operations.hours.update',
        'merchant.operations.service_area.view',
        'merchant.operations.service_area.update',
        'merchant.operations.availability.view',
        'merchant.operations.catalog.view',
        'merchant.operations.catalog.availability.update',
        'merchant.operations.catalog.assignment.status.update',
        'merchant.operations.catalog.order.update',
    ]);
});

it('resolves the read-only capability list for outlet staff', function () {
    $resolver = new OutletRoleCapabilityResolver;

    expect($resolver->permissionsFor(OutletUserRole::OutletStaff))->toBe([
        'merchant.operations.view',
        'merchant.operations.outlets.view',
        'merchant.operations.hours.view',
        'merchant.operations.service_area.view',
        'merchant.operations.availability.view',
        'merchant.operations.catalog.view',
        'merchant.operations.catalog.availability.update',
    ]);
});

it('allows a capability only for the role that holds it', function () {
    $resolver = new OutletRoleCapabilityResolver;

    expect($resolver->allows(OutletUserRole::OutletManager, 'merchant.operations.hours.update'))->toBeTrue()
        ->and($resolver->allows(OutletUserRole::OutletStaff, 'merchant.operations.hours.update'))->toBeFalse()
        ->and($resolver->allows(OutletUserRole::OutletStaff, 'merchant.operations.hours.view'))->toBeTrue()
        ->and($resolver->allows(OutletUserRole::OutletManager, 'merchant.operations.outlet_users.assign'))->toBeTrue()
        ->and($resolver->allows(OutletUserRole::OutletStaff, 'merchant.operations.outlet_users.assign'))->toBeFalse()
        ->and($resolver->allows(OutletUserRole::OutletManager, 'merchant.operations.does_not_exist'))->toBeFalse();
});

it('grants every staff capability to a manager as well', function () {
    $resolver = new OutletRoleCapabilityResolver;

    $staffCapabilities = $resolver->permissionsFor(OutletUserRole::OutletStaff);

    foreach ($staffCapabilities as $capability) {
        expect($resolver->allows(OutletUserRole::OutletManager, $capability))->toBeTrue();
    }
});
