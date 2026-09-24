<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesModifierGroups;
use App\Modules\Merchant\Domain\Catalog\ModifierSelectionRule;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class UpdateModifierGroup
{
    use ManagesModifierGroups;

    /**
     * Selection rules are evaluated against the resulting state (existing
     * record merged with the payload), not just the submitted fields.
     *
     * @param  array{name?: string, description?: string|null, selection_type?: string, min_selection?: int|null, max_selection?: int|null, is_required?: bool|null, display_order?: int|null}  $data
     */
    public function __invoke(ProductModifierGroup $group, array $data): Result
    {
        return DB::transaction(function () use ($group, $data): Result {
            $locked = $this->lockGroup($group);

            $selectionType = array_key_exists('selection_type', $data)
                ? ModifierSelectionType::from($data['selection_type'])
                : $locked->selection_type;

            $minProvided = array_key_exists('min_selection', $data) && $data['min_selection'] !== null;
            $requiredProvided = array_key_exists('is_required', $data) && $data['is_required'] !== null;
            $maxProvided = array_key_exists('max_selection', $data) && $data['max_selection'] !== null;

            $minSelection = $minProvided ? (int) $data['min_selection'] : $locked->min_selection;

            if (! $minProvided && $requiredProvided) {
                $minSelection = (bool) $data['is_required'] ? 1 : 0;
            }

            $isRequired = ModifierSelectionRule::isRequiredFor(
                $requiredProvided ? (bool) $data['is_required'] : null,
                $minSelection,
            );

            if ($selectionType === ModifierSelectionType::Single) {
                $maxSelection = ModifierSelectionRule::normalizeMax(
                    $selectionType,
                    $maxProvided ? (int) $data['max_selection'] : null,
                );
            } else {
                $maxSelection = $maxProvided ? (int) $data['max_selection'] : $locked->max_selection;
            }

            $errors = ModifierSelectionRule::validate($selectionType, $minSelection, $maxSelection, $isRequired);

            if ($errors !== []) {
                return $this->catalogValidationError($errors);
            }

            if ($this->changingToSingle($locked, $selectionType) && $this->defaultCount($locked) > 1) {
                return $this->catalogValidationError([
                    'selection_type' => ['A single selection group cannot have more than one default modifier.'],
                ]);
            }

            if ($locked->status === CatalogStatus::Active) {
                if ($error = $this->assertGroupInvariant($locked, minSelection: $minSelection)) {
                    return $error;
                }
            }

            $locked->fill([
                'name' => $data['name'] ?? $locked->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $locked->description,
                'selection_type' => $selectionType,
                'min_selection' => $minSelection,
                'max_selection' => $maxSelection,
                'is_required' => $isRequired,
                'display_order' => $data['display_order'] ?? $locked->display_order,
            ])->save();

            return Result::ok($locked->refresh());
        });
    }

    private function changingToSingle(ProductModifierGroup $group, ModifierSelectionType $selectionType): bool
    {
        return $selectionType === ModifierSelectionType::Single
            && $group->selection_type !== ModifierSelectionType::Single;
    }

    private function defaultCount(ProductModifierGroup $group): int
    {
        return ProductModifier::query()
            ->where('modifier_group_id', $group->id)
            ->where('is_default', true)
            ->count();
    }
}
