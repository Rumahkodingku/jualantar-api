<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductVariants;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class DeactivateProductVariant
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

            // A deactivated variant cannot stay the default; there is no
            // auto-promotion of another variant (D12).
            $variant->update([
                'status' => CatalogStatus::Inactive,
                'is_default' => false,
            ]);

            return Result::ok($variant->refresh());
        });
    }
}
