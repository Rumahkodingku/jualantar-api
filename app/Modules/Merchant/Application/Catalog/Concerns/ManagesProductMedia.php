<?php

namespace App\Modules\Merchant\Application\Catalog\Concerns;

use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;

trait ManagesProductMedia
{
    use ManagesProductDrafts;
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
     * Object keys for this product are minted by the server as
     * merchants/{merchant_id}/products/{product_id}/{uuid}.{ext}.
     */
    private function objectKeyPrefix(Product $product): string
    {
        return "merchants/{$product->merchant_id}/products/{$product->id}/";
    }

    /**
     * Anything outside the product prefix is rejected so a client can never
     * attach a file it does not own.
     *
     * Photos staged in the "add product" wizard are uploaded before the product
     * exists, so they live under the merchant's draft prefix instead. Those keys
     * are accepted only while the merchant's own draft still records them, and
     * the storage checks in the registering action still apply unchanged.
     */
    private function isOwnedObjectKey(Product $product, string $objectKey): bool
    {
        if (str_starts_with($objectKey, $this->objectKeyPrefix($product))) {
            return true;
        }

        $merchant = $product->merchant;

        if ($merchant === null) {
            return false;
        }

        return $this->isOwnedDraftObjectKey($merchant, $objectKey);
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
