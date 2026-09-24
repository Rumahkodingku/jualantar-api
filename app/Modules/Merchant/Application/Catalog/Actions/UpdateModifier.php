<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesModifierGroups;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateModifier
{
    use ManagesModifierGroups;

    /**
     * @param  array{name?: string, description?: string|null, price?: int|float|string, is_default?: bool|null, display_order?: int|null}  $data
     */
    public function __invoke(ProductModifierGroup $group, ProductModifier $modifier, array $data): Result
    {
        return DB::transaction(function () use ($group, $modifier, $data): Result {
            $wantsDefault = array_key_exists('is_default', $data) && $data['is_default'] !== null
                ? (bool) $data['is_default']
                : null;

            if ($wantsDefault === true) {
                if ($modifier->status !== CatalogStatus::Active) {
                    return $this->catalogValidationError([
                        'is_default' => ['Only an active modifier can be the default.'],
                    ]);
                }

                if ($error = $this->assertDefaultLimit($group, $modifier->id)) {
                    return $error;
                }
            }

            $modifier->fill(Arr::only($data, ['name', 'description', 'price', 'display_order']))->save();

            if ($wantsDefault === true) {
                $this->makeDefault($group, $modifier);
            } elseif ($wantsDefault === false) {
                $modifier->update(['is_default' => false]);
            }

            return Result::ok($modifier->refresh());
        });
    }
}
