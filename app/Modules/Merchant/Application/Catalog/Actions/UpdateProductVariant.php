<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductVariants;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateProductVariant
{
    use ManagesProductVariants;

    /**
     * @param  array{name?: string, price?: int|float|string, sku?: string|null, display_order?: int|null, is_default?: bool|null}  $data
     */
    public function __invoke(Product $product, ProductVariant $variant, array $data): Result
    {
        if ($error = $this->assertVariableProduct($product)) {
            return $error;
        }

        return DB::transaction(function () use ($variant, $data): Result {
            $wantsDefault = array_key_exists('is_default', $data) && (bool) $data['is_default'];

            if ($wantsDefault && $variant->status !== CatalogStatus::Active) {
                return $this->catalogValidationError([
                    'is_default' => ['Only an active variant can be the default.'],
                ]);
            }

            $variant->fill(Arr::only($data, ['name', 'sku', 'price', 'display_order']));
            $variant->save();

            if (array_key_exists('is_default', $data)) {
                if ($wantsDefault) {
                    $this->makeDefault($variant);
                } else {
                    $variant->update(['is_default' => false]);
                }
            }

            return Result::ok($variant->refresh());
        });
    }
}
