<?php

use App\Modules\Merchant\Domain\Catalog\OutletItemStatus;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;

it('lets the master status act as a ceiling that an outlet cannot lift', function () {
    expect(OutletItemStatus::resolve(CatalogStatus::Inactive, null))->toBe(CatalogStatus::Inactive)
        ->and(OutletItemStatus::resolve(CatalogStatus::Inactive, CatalogStatus::Inactive))->toBe(CatalogStatus::Inactive)
        ->and(OutletItemStatus::resolve(CatalogStatus::Inactive, CatalogStatus::Active))->toBe(CatalogStatus::Inactive);
});

it('lets an outlet only restrict a master-active item', function () {
    expect(OutletItemStatus::resolve(CatalogStatus::Active, null))->toBe(CatalogStatus::Active)
        ->and(OutletItemStatus::resolve(CatalogStatus::Active, CatalogStatus::Inactive))->toBe(CatalogStatus::Inactive)
        ->and(OutletItemStatus::resolve(CatalogStatus::Active, CatalogStatus::Active))->toBe(CatalogStatus::Active);
});

it('reports whether an item is effective-active at the outlet', function () {
    expect(OutletItemStatus::isEffectiveActive(CatalogStatus::Active, null))->toBeTrue()
        ->and(OutletItemStatus::isEffectiveActive(CatalogStatus::Active, CatalogStatus::Inactive))->toBeFalse()
        ->and(OutletItemStatus::isEffectiveActive(CatalogStatus::Inactive, null))->toBeFalse();
});

it('treats only a recorded row as an override', function () {
    expect(OutletItemStatus::isOverridden(null))->toBeFalse()
        ->and(OutletItemStatus::isOverridden(CatalogStatus::Inactive))->toBeTrue()
        ->and(OutletItemStatus::isOverridden(CatalogStatus::Active))->toBeTrue();
});
