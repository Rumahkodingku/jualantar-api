<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Shared\Result\Result;

final class ActivateCategory
{
    public function __invoke(CatalogCategory $category): Result
    {
        $category->update(['status' => CatalogStatus::Active]);

        return Result::ok($category->refresh());
    }
}
