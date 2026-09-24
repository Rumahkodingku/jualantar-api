<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Shared\Result\Result;
use Illuminate\Support\Arr;

final class UpdateCategory
{
    /**
     * @param  array{name?: string, description?: string|null, display_order?: int|null}  $data
     */
    public function __invoke(CatalogCategory $category, array $data): Result
    {
        $category->fill(Arr::only($data, ['name', 'description', 'display_order']));
        $category->save();

        return Result::ok($category->refresh());
    }
}
