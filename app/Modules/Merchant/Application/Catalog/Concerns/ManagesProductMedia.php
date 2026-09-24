<?php

namespace App\Modules\Merchant\Application\Catalog\Concerns;

use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;

trait ManagesProductMedia
{
    use ReportsCatalogErrors;

    private function mediaCount(Product $product): int
    {
        return ProductMedia::query()->where('product_id', $product->id)->count();
    }

    private function mediaLimit(): int
    {
        return (int) config('storage.uploads.max_product_media', 10);
    }

    /**
     * Object keys are minted by the server as
     * merchants/{merchant_id}/products/{product_id}/{uuid}.{ext}; anything
     * outside that prefix is rejected so a client can never attach a file it
     * does not own.
     */
    private function objectKeyPrefix(Product $product): string
    {
        return "merchants/{$product->merchant_id}/products/{$product->id}/";
    }

    private function isOwnedObjectKey(Product $product, string $objectKey): bool
    {
        return str_starts_with($objectKey, $this->objectKeyPrefix($product));
    }

    private function nextDisplayOrder(Product $product): int
    {
        return (int) ProductMedia::query()
            ->where('product_id', $product->id)
            ->max('display_order') + 1;
    }

    private function clearPrimary(Product $product): void
    {
        ProductMedia::query()
            ->where('product_id', $product->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
