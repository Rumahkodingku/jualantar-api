<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductVariants;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class DeleteProductVariant
{
    use ManagesProductVariants;

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

            $variant->forceFill(['is_default' => false])->save();
            $variant->delete();

            return Result::ok(null);
        });
    }
}
