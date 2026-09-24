<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesModifierGroups;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class CreateModifier
{
    use ManagesModifierGroups;

    /**
     * @param  array{name: string, description?: string|null, price: int|float|string, is_default?: bool|null, display_order?: int|null}  $data
     */
    public function __invoke(ProductModifierGroup $group, array $data): Result
    {
        return DB::transaction(function () use ($group, $data): Result {
            if ($error = $this->assertModifierLimit($group)) {
                return $error;
            }

            $wantsDefault = (bool) ($data['is_default'] ?? false);

            if ($wantsDefault) {
                if ($error = $this->assertDefaultLimit($group)) {
                    return $error;
                }
            }

            $modifier = ProductModifier::query()->create([
                'merchant_id' => $group->merchant_id,
                'modifier_group_id' => $group->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'status' => CatalogStatus::Active,
                'is_default' => false,
                'display_order' => $data['display_order'] ?? $this->nextDisplayOrder($group),
            ]);

            if ($wantsDefault) {
                $this->makeDefault($group, $modifier);
            }

            return Result::ok($modifier->refresh());
        });
    }

    private function nextDisplayOrder(ProductModifierGroup $group): int
    {
        return (int) ProductModifier::query()
            ->where('modifier_group_id', $group->id)
            ->max('display_order') + 1;
    }
}
