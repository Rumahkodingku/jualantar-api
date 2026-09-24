<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesModifierGroups;
use App\Modules\Merchant\Domain\Catalog\ModifierSelectionRule;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;

final class CreateModifierGroup
{
    use ManagesModifierGroups;

    /**
     * @param  array{name: string, description?: string|null, selection_type: string, min_selection?: int|null, max_selection?: int|null, is_required?: bool|null, display_order?: int|null}  $data
     */
    public function __invoke(Product $product, array $data): Result
    {
        if ($error = $this->assertGroupLimit($product)) {
            return $error;
        }

        $selectionType = ModifierSelectionType::from($data['selection_type']);
        $minProvided = array_key_exists('min_selection', $data) && $data['min_selection'] !== null;
        $requiredProvided = array_key_exists('is_required', $data) && $data['is_required'] !== null;

        $minSelection = $minProvided
            ? (int) $data['min_selection']
            : ($requiredProvided && (bool) $data['is_required'] ? 1 : 0);

        $isRequired = ModifierSelectionRule::isRequiredFor(
            $requiredProvided ? (bool) $data['is_required'] : null,
            $minSelection,
        );

        $maxSelection = ModifierSelectionRule::normalizeMax(
            $selectionType,
            array_key_exists('max_selection', $data) && $data['max_selection'] !== null
                ? (int) $data['max_selection']
                : null,
        );

        $errors = ModifierSelectionRule::validate($selectionType, $minSelection, $maxSelection, $isRequired);

        if ($errors !== []) {
            return $this->catalogValidationError($errors);
        }

        $group = ProductModifierGroup::query()->create([
            'merchant_id' => $product->merchant_id,
            'product_id' => $product->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'selection_type' => $selectionType,
            'min_selection' => $minSelection,
            'max_selection' => $maxSelection,
            'is_required' => $isRequired,
            'status' => CatalogStatus::Inactive,
            'display_order' => $data['display_order'] ?? $this->nextDisplayOrder($product),
        ]);

        return Result::ok($group);
    }

    private function nextDisplayOrder(Product $product): int
    {
        return (int) ProductModifierGroup::query()
            ->where('product_id', $product->id)
            ->max('display_order') + 1;
    }
}
