<?php

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Domain\Models\ProductVariant;

it('casts catalog attributes to enums and decimals', function () {
    $merchant = $this->newMerchant();
    $category = $this->newCatalogCategory(['merchant_id' => $merchant->id]);
    $product = $this->newProduct([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'product_type' => ProductType::Simple,
        'price' => 5000,
    ]);

    expect($category->fresh()->status)->toBe(CatalogStatus::Active)
        ->and($product->fresh()->product_type)->toBe(ProductType::Simple)
        ->and($product->fresh()->status)->toBe(CatalogStatus::Inactive)
        ->and($product->fresh()->price)->toBe('5000.00');
});

it('links the product to its category, variants and media', function () {
    $merchant = $this->newMerchant();
    $category = $this->newCatalogCategory(['merchant_id' => $merchant->id]);
    $product = $this->newProduct([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'product_type' => ProductType::Variable,
        'price' => null,
    ]);

    $variant = $this->newProductVariant(['merchant_id' => $merchant->id, 'product_id' => $product->id]);
    $media = $this->newProductMedia(['merchant_id' => $merchant->id, 'product_id' => $product->id]);

    $product->refresh();

    expect($product->category->id)->toBe($category->id)
        ->and($product->variants->pluck('id')->all())->toBe([$variant->id])
        ->and($product->media->pluck('id')->all())->toBe([$media->id])
        ->and($variant->product->id)->toBe($product->id);
});

it('casts the outlet assignment availability', function () {
    $merchant = $this->newMerchant();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $product = $this->newProduct(['merchant_id' => $merchant->id]);

    $assignment = $this->newOutletProduct([
        'merchant_id' => $merchant->id,
        'outlet_id' => $outlet->id,
        'product_id' => $product->id,
        'availability_status' => ProductAvailabilityStatus::Unavailable,
        'unavailable_reason' => 'Stok habis',
    ]);

    expect($assignment->fresh()->status)->toBe(CatalogStatus::Active)
        ->and($assignment->fresh()->availability_status)->toBe(ProductAvailabilityStatus::Unavailable)
        ->and($assignment->fresh()->unavailable_reason)->toBe('Stok habis');
});

it('hides soft deleted rows from default queries', function () {
    $media = $this->newProductMedia();
    $media->delete();

    expect(ProductMedia::query()->find($media->id))->toBeNull()
        ->and(ProductMedia::withTrashed()->find($media->id))->not->toBeNull();
});

it('allows reusing a variant sku once the previous variant was deleted', function () {
    $product = $this->newProduct(['product_type' => ProductType::Variable, 'price' => null]);

    $original = $this->newProductVariant([
        'merchant_id' => $product->merchant_id,
        'product_id' => $product->id,
        'sku' => 'ICE-STRAWBERRY',
    ]);

    $original->delete();

    $recreated = $this->newProductVariant([
        'merchant_id' => $product->merchant_id,
        'product_id' => $product->id,
        'sku' => 'ICE-STRAWBERRY',
    ]);

    expect($recreated->sku)->toBe('ICE-STRAWBERRY')
        ->and(ProductVariant::query()->count())->toBe(1);
});
