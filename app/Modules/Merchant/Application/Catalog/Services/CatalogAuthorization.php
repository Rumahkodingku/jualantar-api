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
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Database\Eloquent\Model;

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

    public function modifierGroup(Product $product, string $groupId): Result
    {
        $group = ProductModifierGroup::query()
            ->where('product_id', $product->id)
            ->where('merchant_id', $product->merchant_id)
            ->find($groupId);

        if ($group === null) {
            return $this->catalogNotFound('The modifier group was not found.');
        }

        return Result::ok($group);
    }

    public function modifier(ProductModifierGroup $group, string $modifierId): Result
    {
        $modifier = ProductModifier::query()
            ->where('modifier_group_id', $group->id)
            ->where('merchant_id', $group->merchant_id)
            ->find($modifierId);

        if ($modifier === null) {
            return $this->catalogNotFound('The modifier was not found.');
        }

        return Result::ok($modifier);
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

    /**
     * Authorize an outlet-scoped read and return the product assigned to that
     * outlet. Reuses the assignment contract so a foreign outlet stays a 404, a
     * same-merchant outlet outside the assignment scope stays a 403, and a
     * product without an assignment to the outlet stays a 404. The employee
     * never reaches the owner-only master product detail through this path.
     */
    public function outletProduct(string $outletId, string $productId, string $capability): Result
    {
        $assignmentResult = $this->assignmentForOutletAction($outletId, $productId, $capability);

        if ($assignmentResult->isErr()) {
            return $assignmentResult;
        }

        /** @var OutletProduct $assignment */
        $assignment = $assignmentResult->unwrap();

        return Result::ok($assignment->product);
    }

    /**
     * Authorize an outlet-scoped status override and return the assignment
     * together with the one variant of that product.
     *
     * The variant is resolved inside the outlet's own merchant, so a UUID from
     * another merchant, or one belonging to a different product of the same
     * merchant, is a 404 rather than a leak.
     *
     * @return Result<array{0: OutletProduct, 1: ProductVariant}>
     */
    public function outletVariant(
        string $outletId,
        string $productId,
        string $variantId,
        string $capability,
    ): Result {
        return $this->withOutletItem(
            $outletId,
            $productId,
            $capability,
            fn (Product $product): mixed => $this->variant($product, $variantId),
            ProductVariant::class,
        );
    }

    /**
     * Authorize an outlet-scoped status override on one modifier group.
     *
     * @return Result<array{0: OutletProduct, 1: ProductModifierGroup}>
     */
    public function outletModifierGroup(
        string $outletId,
        string $productId,
        string $groupId,
        string $capability,
    ): Result {
        return $this->withOutletItem(
            $outletId,
            $productId,
            $capability,
            fn (Product $product): mixed => $this->modifierGroup($product, $groupId),
            ProductModifierGroup::class,
        );
    }

    /**
     * Authorize an outlet-scoped status override on one modifier option, nested
     * under its group so a group from another product is a 404 as well.
     *
     * @return Result<array{0: OutletProduct, 1: ProductModifierGroup, 2: ProductModifier}>
     */
    public function outletModifier(
        string $outletId,
        string $productId,
        string $groupId,
        string $modifierId,
        string $capability,
    ): Result {
        $assignmentResult = $this->assignmentForOutletAction($outletId, $productId, $capability);

        if ($assignmentResult->isErr()) {
            return $assignmentResult;
        }

        /** @var OutletProduct $assignment */
        $assignment = $assignmentResult->unwrap();

        $groupResult = $this->modifierGroup($assignment->product, $groupId);

        if ($groupResult->isErr()) {
            return $groupResult;
        }

        /** @var ProductModifierGroup $group */
        $group = $groupResult->unwrap();

        $modifierResult = $this->modifier($group, $modifierId);

        if ($modifierResult->isErr()) {
            return $modifierResult;
        }

        /** @var ProductModifier $modifier */
        $modifier = $modifierResult->unwrap();

        return Result::ok([$assignment, $group, $modifier]);
    }

    /**
     * Authorize the assignment, then resolve one of the product's own items
     * with an owner-scoped lookup reused from the same service.
     *
     * @template TItem of Model
     *
     * @param  callable(Product): Result  $resolve
     * @param  class-string<TItem>  $expected
     * @return Result<array{0: OutletProduct, 1: TItem}>
     */
    private function withOutletItem(
        string $outletId,
        string $productId,
        string $capability,
        callable $resolve,
        string $expected,
    ): Result {
        $assignmentResult = $this->assignmentForOutletAction($outletId, $productId, $capability);

        if ($assignmentResult->isErr()) {
            return $assignmentResult;
        }

        /** @var OutletProduct $assignment */
        $assignment = $assignmentResult->unwrap();

        $itemResult = $resolve($assignment->product);

        if ($itemResult->isErr()) {
            return $itemResult;
        }

        $item = $itemResult->unwrap();

        if (! $item instanceof $expected) {
            return $this->catalogNotFound('The requested catalog item was not found.');
        }

        return Result::ok([$assignment, $item]);
    }
}
