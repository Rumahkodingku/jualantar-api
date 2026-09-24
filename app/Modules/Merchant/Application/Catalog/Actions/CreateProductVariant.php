<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductVariants;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class CreateProductVariant
{
    use ManagesProductVariants;

    /**
     * @param  array{name: string, price: int|float|string, sku?: string|null, display_order?: int|null, is_default?: bool|null}  $data
     */
    public function __invoke(Product $product, array $data): Result
    {
        if ($error = $this->assertVariableProduct($product)) {
            return $error;
        }

        return DB::transaction(function () use ($product, $data): Result {
            $variant = ProductVariant::query()->create([
                'merchant_id' => $product->merchant_id,
                'product_id' => $product->id,
                'sku' => $data['sku'] ?? null,
                'name' => $data['name'],
                'price' => $data['price'],
                'status' => CatalogStatus::Active,
                'is_default' => false,
                'display_order' => $data['display_order'] ?? $this->nextDisplayOrder($product),
            ]);

            if ((bool) ($data['is_default'] ?? false)) {
                $this->makeDefault($variant);
            }

            return Result::ok($variant->refresh());
        });
    }

    private function nextDisplayOrder(Product $product): int
    {
        return (int) ProductVariant::query()
            ->where('product_id', $product->id)
            ->max('display_order') + 1;
    }
}
