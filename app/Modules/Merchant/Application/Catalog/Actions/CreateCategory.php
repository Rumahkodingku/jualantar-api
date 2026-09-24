<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;

final class CreateCategory
{
    /**
     * @param  array{name: string, description?: string|null, display_order?: int|null}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        $category = CatalogCategory::query()->create([
            'merchant_id' => $merchant->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => CatalogStatus::Active,
            'display_order' => $data['display_order'] ?? $this->nextDisplayOrder($merchant),
        ]);

        return Result::ok($category);
    }

    private function nextDisplayOrder(Merchant $merchant): int
    {
        return (int) CatalogCategory::query()
            ->where('merchant_id', $merchant->id)
            ->max('display_order') + 1;
    }
}
