<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductVariants;
use App\Modules\Merchant\Domain\Models\OutletProductVariant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class DeleteProductVariant
{
    use ManagesProductVariants;

    /**
     * The per-outlet overrides of the variant are soft deleted with it: they
     * only exist to deviate from an item that is going away.
     */
    public function __invoke(Product $product, ProductVariant $variant): Result
    {
        if ($error = $this->assertVariableProduct($product)) {
            return $error;
        }

        return DB::transaction(function () use ($product, $variant): Result {
            $locked = $this->lockProduct($product);

            if ($error = $this->guardLastActiveVariant($locked, $variant)) {
                return $error;
            }

            OutletProductVariant::query()->where('product_variant_id', $variant->id)->delete();

            $variant->forceFill(['is_default' => false])->save();
            $variant->delete();

            return Result::ok(null);
        });
    }
}
