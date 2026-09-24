<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Shared\Result\Result;

final class CreateProduct
{
    use ReportsCatalogErrors;

    /**
     * @param  array{category_id: string, name: string, product_type: string, description?: string|null, price?: int|float|string|null, display_order?: int|null}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        $category = CatalogCategory::query()
            ->where('merchant_id', $merchant->id)
            ->find($data['category_id']);

        if ($category === null) {
            return $this->catalogInvalidCategory();
        }

        $type = ProductType::from($data['product_type']);

        $product = Product::query()->create([
            'merchant_id' => $merchant->id,
            'category_id' => $category->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'product_type' => $type,
            'price' => $type === ProductType::Simple ? $data['price'] : null,
            'status' => CatalogStatus::Inactive,
            'display_order' => $data['display_order'] ?? $this->nextDisplayOrder($merchant),
        ]);

        return Result::ok($product);
    }

    private function nextDisplayOrder(Merchant $merchant): int
    {
        return (int) Product::query()
            ->where('merchant_id', $merchant->id)
            ->max('display_order') + 1;
    }
}
