<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Product;
use App\Shared\Result\Result;

final class DeleteCategory
{
    use ReportsCatalogErrors;

    public function __invoke(CatalogCategory $category): Result
    {
        $hasProducts = Product::query()
            ->where('category_id', $category->id)
            ->exists();

        if ($hasProducts) {
            return $this->catalogCategoryInUse();
        }

        $category->delete();

        return Result::ok(null);
    }
}
