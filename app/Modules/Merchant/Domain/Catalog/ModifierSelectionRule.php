<?php

namespace App\Modules\Merchant\Domain\Catalog;

use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;

/**
 * Selection rules for a modifier group (PRD P1 Bagian 7.1).
 *
 * Kept as pure functions so the whole rule table can be exercised without a
 * database, mirroring ProductSellability.
 */
final class ModifierSelectionRule
{
    /**
     * `is_required` mirrors `min_selection >= 1`; when the caller did not send
     * it, it is derived from the effective min selection.
     */
    public static function isRequiredFor(?bool $explicit, int $minSelection): bool
    {
        return $explicit ?? $minSelection >= 1;
    }

    /**
     * A single-choice group is capped at one; an absent max means exactly one.
     */
    public static function normalizeMax(ModifierSelectionType $selectionType, ?int $maxSelection): ?int
    {
        if ($selectionType === ModifierSelectionType::Single) {
            return $maxSelection ?? 1;
        }

        return $maxSelection;
    }

    /**
     * Field-keyed validation errors; an empty array means the state is valid.
     *
     * @return array<string, list<string>>
     */
    public static function validate(
        ModifierSelectionType $selectionType,
        int $minSelection,
        ?int $maxSelection,
        bool $isRequired,
    ): array {
        $errors = [];

        if ($minSelection < 0) {
            $errors['min_selection'][] = 'The min selection must be at least 0.';
        }

        $lowerBound = max($minSelection, 1);

        if ($maxSelection !== null && $maxSelection < $lowerBound) {
            $errors['max_selection'][] = "The max selection must be at least {$lowerBound}.";
        }

        if ($selectionType === ModifierSelectionType::Single && $maxSelection !== 1) {
            $errors['max_selection'][] = 'A single selection group must have a max selection of 1.';
        }

        if ($isRequired !== ($minSelection >= 1)) {
            $errors['is_required'][] = 'The is required flag must match the min selection.';
        }

        return $errors;
    }

    /**
     * Active modifier count a group must expose to be kept active (PRD 7.5).
     *
     * `max(min_selection, 1)` means even an optional group needs at least one
     * active modifier to be active.
     */
    public static function requiredActiveCount(int $minSelection): int
    {
        return max($minSelection, 1);
    }
}
