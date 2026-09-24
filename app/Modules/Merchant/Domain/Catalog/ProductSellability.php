<?php

namespace App\Modules\Merchant\Domain\Catalog;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;

/**
 * Whether a product can actually be sold at one outlet.
 *
 * A product is sellable only when the master product, its category and the
 * outlet assignment are all active, the assignment is available, and a
 * variable product exposes at least one active variant.
 *
 * Kept as a pure function so the rule can be table-tested without a database
 * and reused by the customer endpoints in a later step.
 */
final class ProductSellability
{
    public static function evaluate(
        CatalogStatus $productStatus,
        ?CatalogStatus $categoryStatus,
        CatalogStatus $assignmentStatus,
        ProductAvailabilityStatus $availabilityStatus,
        ProductType $productType,
        int $activeVariantCount,
    ): bool {
        $hasSellableVariant = $productType === ProductType::Simple || $activeVariantCount >= 1;

        return $productStatus === CatalogStatus::Active
            && $categoryStatus === CatalogStatus::Active
            && $assignmentStatus === CatalogStatus::Active
            && $availabilityStatus === ProductAvailabilityStatus::Available
            && $hasSellableVariant;
    }
}
