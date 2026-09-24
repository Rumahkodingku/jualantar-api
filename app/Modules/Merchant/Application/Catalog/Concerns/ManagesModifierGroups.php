<?php

namespace App\Modules\Merchant\Application\Catalog\Concerns;

use App\Modules\Merchant\Domain\Catalog\ModifierSelectionRule;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Result\Result;

/**
 * Shared guards for modifier groups and modifiers: the active-group invariant,
 * the per-product/per-group limits and the default modifier rules.
 */
trait ManagesModifierGroups
{
    use ReportsCatalogErrors;

    private const MAX_GROUPS_PER_PRODUCT = 20;

    private const MAX_MODIFIERS_PER_GROUP = 50;

    /**
     * Lock the group row so the invariant check cannot race a concurrent
     * modifier write.
     */
    private function lockGroup(ProductModifierGroup $group): ProductModifierGroup
    {
        return ProductModifierGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
    }

    private function activeModifierCount(ProductModifierGroup $group, ?string $ignoreModifierId = null): int
    {
        return ProductModifier::query()
            ->where('modifier_group_id', $group->id)
            ->where('status', CatalogStatus::Active->value)
            ->when($ignoreModifierId !== null, fn ($query) => $query->whereKeyNot($ignoreModifierId))
            ->count();
    }

    /**
     * The invariant an active group must satisfy (PRD 7.5): at least
     * max(min_selection, 1) active modifiers must remain selectable. Pass a
     * min selection to check a not-yet-persisted state, and an id to exclude a
     * modifier that is being deactivated or deleted.
     */
    private function assertGroupInvariant(
        ProductModifierGroup $group,
        ?int $minSelection = null,
        ?string $ignoreModifierId = null,
    ): ?Result {
        $required = ModifierSelectionRule::requiredActiveCount($minSelection ?? $group->min_selection);

        if ($this->activeModifierCount($group, $ignoreModifierId) < $required) {
            return $this->modifierGroupInsufficientModifiers();
        }

        return null;
    }

    /**
     * The invariant an active group must satisfy (PRD 7.5) when a modifier is
     * deactivated or deleted: an active group must keep at least
     * max(min_selection, 1) active modifiers. On an inactive group it always
     * holds, so removal is always allowed.
     */
    private function assertModifierRemovalKeepsGroupSatisfiable(
        ProductModifierGroup $group,
        ProductModifier $modifier,
    ): ?Result {
        if ($group->status !== CatalogStatus::Active || $modifier->status !== CatalogStatus::Active) {
            return null;
        }

        $required = ModifierSelectionRule::requiredActiveCount($group->min_selection);

        if ($this->activeModifierCount($group, $modifier->id) < $required) {
            return $this->modifierRequiredByActiveGroup();
        }

        return null;
    }

    private function assertGroupLimit(Product $product): ?Result
    {
        $count = ProductModifierGroup::query()->where('product_id', $product->id)->count();

        if ($count >= self::MAX_GROUPS_PER_PRODUCT) {
            return $this->modifierGroupLimitReached();
        }

        return null;
    }

    private function assertModifierLimit(ProductModifierGroup $group): ?Result
    {
        $count = ProductModifier::query()->where('modifier_group_id', $group->id)->count();

        if ($count >= self::MAX_MODIFIERS_PER_GROUP) {
            return $this->modifierLimitReached();
        }

        return null;
    }

    /**
     * Replace the group default. A single-choice group holds at most one, so
     * the previous default is released before the new one is set.
     */
    private function makeDefault(ProductModifierGroup $group, ProductModifier $modifier): void
    {
        ProductModifier::query()
            ->where('modifier_group_id', $group->id)
            ->whereKeyNot($modifier->id)
            ->update(['is_default' => false]);

        $modifier->update(['is_default' => true]);
    }

    private function resetDefault(ProductModifier $modifier): void
    {
        if ($modifier->is_default) {
            $modifier->update(['is_default' => false]);
        }
    }

    /**
     * A multiple-choice group may hold at most max_selection defaults, where a
     * null max means unlimited. Single-choice groups auto-swap instead. Pass an
     * id to exclude a modifier that already holds a default.
     */
    private function assertDefaultLimit(ProductModifierGroup $group, ?string $ignoreModifierId = null): ?Result
    {
        if ($group->selection_type === ModifierSelectionType::Single || $group->max_selection === null) {
            return null;
        }

        $others = ProductModifier::query()
            ->where('modifier_group_id', $group->id)
            ->where('is_default', true)
            ->when($ignoreModifierId !== null, fn ($query) => $query->whereKeyNot($ignoreModifierId))
            ->count();

        if ($others + 1 > $group->max_selection) {
            return $this->catalogValidationError([
                'is_default' => ['This group already has the maximum number of default modifiers.'],
            ]);
        }

        return null;
    }
}
