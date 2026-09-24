<?php

namespace App\Modules\Merchant\Application\Catalog\Concerns;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;

trait ManagesProductVariants
{
    use ReportsCatalogErrors;

    private function assertVariableProduct(Product $product): ?Result
    {
        if ($product->product_type !== ProductType::Variable) {
            return $this->variantNotAllowedForSimpleProduct();
        }

        return null;
    }

    /**
     * Lock the parent product so the "last active variant" check cannot race a
     * concurrent variant write.
     */
    private function lockProduct(Product $product): Product
    {
        return Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Only protect the last active variant while the product itself is active.
     */
    private function guardLastActiveVariant(Product $product, ProductVariant $variant): ?Result
    {
        if ($product->status !== CatalogStatus::Active || $variant->status !== CatalogStatus::Active) {
            return null;
        }

        $hasOtherActiveVariant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereKeyNot($variant->id)
            ->where('status', CatalogStatus::Active->value)
            ->exists();

        if (! $hasOtherActiveVariant) {
            return $this->lastActiveVariant();
        }

        return null;
    }

    /**
     * Promote a variant to the product default, releasing the previous one
     * first so the partial unique index is never violated mid-transaction.
     */
    private function makeDefault(ProductVariant $variant): void
    {
        ProductVariant::query()
            ->where('product_id', $variant->product_id)
            ->whereKeyNot($variant->id)
            ->update(['is_default' => false]);

        $variant->update(['is_default' => true]);
    }
}
