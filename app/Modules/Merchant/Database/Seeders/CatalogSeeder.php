<?php

namespace App\Modules\Merchant\Database\Seeders;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::query()->orderBy('created_at')->first();

        if ($merchant === null) {
            return;
        }

        DB::transaction(function () use ($merchant): void {
            $drinks = $this->category($merchant, 'Minuman', 1);
            $food = $this->category($merchant, 'Makanan', 2);

            $icedTea = $this->simpleProduct($merchant, $drinks, 'Es Teh', 5_000, 1);
            $friedRice = $this->simpleProduct($merchant, $food, 'Nasi Goreng', 20_000, 1);
            $iceCream = $this->variableProduct($merchant, $food, 'Ice Cream', 2);

            $this->variant($merchant, $iceCream, 'Strawberry', 'ICE-STRAWBERRY', 10_000, 1, true);
            $this->variant($merchant, $iceCream, 'Chocolate', 'ICE-CHOCOLATE', 10_000, 2);
            $this->variant($merchant, $iceCream, 'Blueberry', 'ICE-BLUEBERRY', 12_000, 3);

            $outlet = $merchant->outlets()->orderBy('created_at')->first();

            if ($outlet === null) {
                return;
            }

            $this->assignment($outlet->id, $merchant->id, $icedTea, 1);
            $this->assignment($outlet->id, $merchant->id, $iceCream, 2);
            $this->assignment($outlet->id, $merchant->id, $friedRice, 3);
        });
    }

    private function category(Merchant $merchant, string $name, int $order): CatalogCategory
    {
        return CatalogCategory::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'name' => $name],
            [
                'description' => null,
                'status' => CatalogStatus::Active,
                'display_order' => $order,
            ],
        );
    }

    private function simpleProduct(Merchant $merchant, CatalogCategory $category, string $name, int $price, int $order): Product
    {
        return Product::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'name' => $name],
            [
                'category_id' => $category->id,
                'description' => null,
                'product_type' => ProductType::Simple,
                'price' => $price,
                'status' => CatalogStatus::Active,
                'display_order' => $order,
            ],
        );
    }

    private function variableProduct(Merchant $merchant, CatalogCategory $category, string $name, int $order): Product
    {
        return Product::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'name' => $name],
            [
                'category_id' => $category->id,
                'description' => null,
                'product_type' => ProductType::Variable,
                'price' => null,
                'status' => CatalogStatus::Active,
                'display_order' => $order,
            ],
        );
    }

    private function variant(
        Merchant $merchant,
        Product $product,
        string $name,
        string $sku,
        int $price,
        int $order,
        bool $isDefault = false,
    ): ProductVariant {
        return ProductVariant::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'sku' => $sku],
            [
                'product_id' => $product->id,
                'name' => $name,
                'price' => $price,
                'status' => CatalogStatus::Active,
                'is_default' => $isDefault,
                'display_order' => $order,
            ],
        );
    }

    private function assignment(string $outletId, string $merchantId, Product $product, int $order): OutletProduct
    {
        return OutletProduct::query()->updateOrCreate(
            ['outlet_id' => $outletId, 'product_id' => $product->id],
            [
                'merchant_id' => $merchantId,
                'status' => CatalogStatus::Active,
                'availability_status' => ProductAvailabilityStatus::Available,
                'unavailable_reason' => null,
                'display_order' => $order,
            ],
        );
    }
}
