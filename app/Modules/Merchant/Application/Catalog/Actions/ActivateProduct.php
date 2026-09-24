<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class ActivateProduct
{
    use ReportsCatalogErrors;

    /**
     * A variable product may only be activated when at least one of its
     * variants is active. The product row is locked so a concurrent variant
     * deactivation cannot slip between the check and the update.
     */
    public function __invoke(Product $product): Result
    {
        return DB::transaction(function () use ($product): Result {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($locked->product_type === ProductType::Variable) {
                $activeVariants = ProductVariant::query()
                    ->where('product_id', $locked->id)
                    ->where('status', CatalogStatus::Active->value)
                    ->count();

                if ($activeVariants < 1) {
                    return $this->variableProductRequiresActiveVariant();
                }
            }

            $locked->update(['status' => CatalogStatus::Active]);

            return Result::ok($locked->refresh());
        });
    }
}
