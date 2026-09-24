<?php

namespace App\Modules\Merchant\Application\Catalog\Services;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;

/**
 * Resolves catalog resources inside the authenticated merchant scope.
 *
 * Owner-only resources are looked up through the owned merchant so a UUID from
 * another merchant is indistinguishable from a missing one. Outlet-scoped
 * actions delegate to MerchantOperationsAuthorization so the contextual outlet
 * roles and the 403 (same merchant, other outlet) versus 404 (foreign merchant)
 * contract stay in one place.
 */
final class CatalogAuthorization
{
    use ReportsCatalogErrors;

    public function __construct(
        private readonly MerchantOperationsAuthorization $operations,
    ) {}

    /**
     * The merchant owned by the authenticated user (owner-only catalog).
     */
    public function merchant(): Result
    {
        return $this->operations->ownedMerchant();
    }

    public function category(string $categoryId): Result
    {
        $merchantResult = $this->operations->ownedMerchant();

        if ($merchantResult->isErr()) {
            return $merchantResult;
        }

        /** @var Merchant $merchant */
        $merchant = $merchantResult->unwrap();

        $category = CatalogCategory::query()
            ->where('merchant_id', $merchant->id)
            ->find($categoryId);

        if ($category === null) {
            return $this->catalogNotFound('The category was not found.');
        }

        return Result::ok($category);
    }

    public function product(string $productId): Result
    {
        $merchantResult = $this->operations->ownedMerchant();

        if ($merchantResult->isErr()) {
            return $merchantResult;
        }

        /** @var Merchant $merchant */
        $merchant = $merchantResult->unwrap();

        return $this->productForMerchant($merchant, $productId);
    }

    public function productForMerchant(Merchant $merchant, string $productId): Result
    {
        $product = Product::query()
            ->where('merchant_id', $merchant->id)
            ->find($productId);

        if ($product === null) {
            return $this->catalogNotFound('The product was not found.');
        }

        return Result::ok($product);
    }

    public function variant(Product $product, string $variantId): Result
    {
        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('merchant_id', $product->merchant_id)
            ->find($variantId);

        if ($variant === null) {
            return $this->catalogNotFound('The variant was not found.');
        }

        return Result::ok($variant);
    }

    public function media(Product $product, string $mediaId): Result
    {
        $media = ProductMedia::query()
            ->where('product_id', $product->id)
            ->where('merchant_id', $product->merchant_id)
            ->find($mediaId);

        if ($media === null) {
            return $this->catalogNotFound('The media was not found.');
        }

        return Result::ok($media);
    }

    /**
     * Resolve the assignment of a product to one of the owner's outlets.
     */
    public function assignmentForOwner(string $productId, string $outletId): Result
    {
        $productResult = $this->product($productId);

        if ($productResult->isErr()) {
            return $productResult;
        }

        /** @var Product $product */
        $product = $productResult->unwrap();

        $outlet = MerchantOutlet::query()
            ->where('merchant_id', $product->merchant_id)
            ->find($outletId);

        if ($outlet === null) {
            return $this->catalogNotFound('The outlet was not found.');
        }

        $assignment = OutletProduct::query()
            ->where('product_id', $product->id)
            ->where('outlet_id', $outlet->id)
            ->first();

        if ($assignment === null) {
            return $this->catalogNotFound('The product is not assigned to this outlet.');
        }

        return Result::ok($assignment);
    }

    /**
     * Authorize an outlet-scoped catalog capability and resolve the matching
     * assignment. The outlet is authorized first so a foreign outlet stays a
     * 404 and a same-merchant outlet outside the assignment scope stays a 403;
     * the product is then looked up inside the outlet's own merchant.
     */
    public function assignmentForOutletAction(string $outletId, string $productId, string $capability): Result
    {
        $outletResult = $this->operations->authorizeOutletAction($outletId, $capability);

        if ($outletResult->isErr()) {
            return $outletResult;
        }

        /** @var MerchantOutlet $outlet */
        $outlet = $outletResult->unwrap();

        $product = Product::query()
            ->where('merchant_id', $outlet->merchant_id)
            ->find($productId);

        if ($product === null) {
            return $this->catalogNotFound('The product was not found.');
        }

        $assignment = OutletProduct::query()
            ->where('product_id', $product->id)
            ->where('outlet_id', $outlet->id)
            ->first();

        if ($assignment === null) {
            return $this->catalogNotFound('The product is not assigned to this outlet.');
        }

        return Result::ok($assignment);
    }

    /**
     * Authorize an outlet-scoped catalog capability and return the outlet, for
     * endpoints that work on the outlet itself (the outlet catalog).
     */
    public function outletCatalog(string $outletId, string $capability): Result
    {
        return $this->operations->authorizeOutletAction($outletId, $capability);
    }
}
