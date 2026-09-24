<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\Product;
use App\Shared\Result\Result;

final class DeactivateProduct
{
    public function __invoke(Product $product): Result
    {
        $product->update(['status' => CatalogStatus::Inactive]);

        return Result::ok($product->refresh());
    }
}
