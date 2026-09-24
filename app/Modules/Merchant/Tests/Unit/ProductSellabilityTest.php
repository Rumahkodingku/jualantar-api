<?php

use App\Modules\Merchant\Domain\Catalog\ProductSellability;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;

/*
|--------------------------------------------------------------------------
| is_sellable (PRD Bagian 4.5)
|--------------------------------------------------------------------------
|
| A product is sellable when the product, its category and the outlet
| assignment are active, the assignment is available, and a variable product
| exposes at least one active variant.
|
*/

it('requires every active layer for a simple product', function () {
    $evaluate = fn (
        CatalogStatus $product = CatalogStatus::Active,
        ?CatalogStatus $category = CatalogStatus::Active,
        CatalogStatus $assignment = CatalogStatus::Active,
        ProductAvailabilityStatus $availability = ProductAvailabilityStatus::Available,
        ProductType $type = ProductType::Simple,
        int $activeVariants = 0,
    ): bool => ProductSellability::evaluate($product, $category, $assignment, $availability, $type, $activeVariants);

    expect($evaluate())->toBeTrue()
        ->and($evaluate(product: CatalogStatus::Inactive))->toBeFalse()
        ->and($evaluate(category: CatalogStatus::Inactive))->toBeFalse()
        ->and($evaluate(category: null))->toBeFalse()
        ->and($evaluate(assignment: CatalogStatus::Inactive))->toBeFalse()
        ->and($evaluate(availability: ProductAvailabilityStatus::Unavailable))->toBeFalse();
});

it('treats a simple product as sellable without variants', function () {
    $sellable = ProductSellability::evaluate(
        CatalogStatus::Active,
        CatalogStatus::Active,
        CatalogStatus::Active,
        ProductAvailabilityStatus::Available,
        ProductType::Simple,
        0,
    );

    expect($sellable)->toBeTrue();
});

it('requires at least one active variant for a variable product', function () {
    $evaluate = fn (int $activeVariants): bool => ProductSellability::evaluate(
        CatalogStatus::Active,
        CatalogStatus::Active,
        CatalogStatus::Active,
        ProductAvailabilityStatus::Available,
        ProductType::Variable,
        $activeVariants,
    );

    expect($evaluate(0))->toBeFalse()
        ->and($evaluate(1))->toBeTrue()
        ->and($evaluate(3))->toBeTrue();
});

it('rejects a variable product whose layers are active but has no active variant', function () {
    $sellable = ProductSellability::evaluate(
        CatalogStatus::Active,
        CatalogStatus::Active,
        CatalogStatus::Active,
        ProductAvailabilityStatus::Available,
        ProductType::Variable,
        0,
    );

    expect($sellable)->toBeFalse();
});
