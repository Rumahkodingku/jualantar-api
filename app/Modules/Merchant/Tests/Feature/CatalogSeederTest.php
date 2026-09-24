<?php

use App\Modules\Merchant\Database\Seeders\CatalogSeeder;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;

it('does nothing when there is no merchant to seed', function () {
    $this->seed(CatalogSeeder::class);

    expect(CatalogCategory::query()->count())->toBe(0);
});

it('seeds an example catalog for the first merchant and stays idempotent', function () {
    $merchant = $this->newMerchant();
    MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $this->seed(CatalogSeeder::class);

    expect(CatalogCategory::query()->count())->toBe(2)
        ->and(Product::query()->count())->toBe(3)
        ->and(ProductVariant::query()->count())->toBe(3)
        ->and(OutletProduct::query()->count())->toBe(3)
        ->and(ProductModifierGroup::query()->count())->toBe(2)
        ->and(ProductModifier::query()->count())->toBe(5);

    $this->seed(CatalogSeeder::class);

    expect(CatalogCategory::query()->count())->toBe(2)
        ->and(Product::query()->count())->toBe(3)
        ->and(ProductVariant::query()->count())->toBe(3)
        ->and(OutletProduct::query()->count())->toBe(3)
        ->and(ProductModifierGroup::query()->count())->toBe(2)
        ->and(ProductModifier::query()->count())->toBe(5);
});
