<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Product;
use App\Shared\Result\Result;
use Illuminate\Support\Arr;

final class UpdateProduct
{
    use ReportsCatalogErrors;

    /**
     * @param  array{category_id?: string, name?: string, description?: string|null, price?: int|float|string|null, display_order?: int|null}  $data
     */
    public function __invoke(Product $product, array $data): Result
    {
        $attributes = Arr::only($data, ['name', 'description', 'display_order']);

        if (array_key_exists('category_id', $data)) {
            $category = CatalogCategory::query()
                ->where('merchant_id', $product->merchant_id)
                ->find($data['category_id']);

            if ($category === null) {
                return $this->catalogInvalidCategory();
            }

            $attributes['category_id'] = $category->id;
        }

        if ($product->product_type === ProductType::Simple && array_key_exists('price', $data)) {
            $attributes['price'] = $data['price'];
        }

        $product->fill($attributes);
        $product->save();

        return Result::ok($product->refresh());
    }
}
