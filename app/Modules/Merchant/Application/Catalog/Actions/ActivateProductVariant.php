<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductVariants;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;

final class ActivateProductVariant
{
    use ManagesProductVariants;

    public function __invoke(Product $product, ProductVariant $variant): Result
    {
        if ($error = $this->assertVariableProduct($product)) {
            return $error;
        }

        $variant->update(['status' => CatalogStatus::Active]);

        return Result::ok($variant->refresh());
    }
}
