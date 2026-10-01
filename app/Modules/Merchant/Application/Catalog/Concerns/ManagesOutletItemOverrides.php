<?php

namespace App\Modules\Merchant\Application\Catalog\Concerns;

use App\Modules\Merchant\Domain\Catalog\ModifierSelectionRule;
use App\Modules\Merchant\Domain\Catalog\OutletItemStatus;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\OutletProductModifier;
use App\Modules\Merchant\Domain\Models\OutletProductModifierGroup;
use App\Modules\Merchant\Domain\Models\OutletProductVariant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared guards and writes for the per-outlet status overrides.
 *
 * An override is a restriction, never a promotion: the master status stays the
 * ceiling and only an `inactive` row is written. Because of that, "activating"
 * an item at an outlet means clearing its override so the item follows the
 * master again.
 *
 * The guards mirror the master ones (`ManagesProductVariants`,
 * `ManagesModifierGroups`) but count *effective* actives at one outlet, so a
 * manager cannot quietly make a sellable product unsellable at their outlet.
 */
trait ManagesOutletItemOverrides
{
    use ReportsCatalogErrors;

    /**
     * Lock the assignment row so two managers working on the same outlet cannot
     * both pass an invariant check and then write conflicting overrides.
     */
    private function lockAssignment(OutletProduct $assignment): OutletProduct
    {
        return OutletProduct::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * A master-inactive item cannot be overridden: the owner already decided it
     * is out of the catalog, so there is nothing for an outlet to deviate from.
     */
    private function assertOverrideAllowed(CatalogStatus $master): ?Result
    {
        return $master === CatalogStatus::Active ? null : $this->outletItemMasterInactive();
    }

    /**
     * Refuse to hide the last variant that keeps the product sellable here.
     *
     * Only enforced while the product is actually listed and sellable at this
     * outlet: once the outlet has already turned the product off or marked it
     * unavailable, the variant count no longer decides sellability and the
     * manager is free to organise the variants any way they like.
     */
    private function guardOutletLastSellableVariant(
        OutletProduct $assignment,
        Product $product,
        ProductVariant $variant,
    ): ?Result {
        if ($variant->status !== CatalogStatus::Active || ! $this->isSellableAtOutlet($assignment, $product)) {
            return null;
        }

        $remaining = $this->effectiveActiveVariantCount($assignment, $product, $variant->id);

        return $remaining === 0 ? $this->outletLastSellableVariant() : null;
    }

    /**
     * Refuse to hide an option an effective-active group needs at this outlet.
     *
     * Follows the master rule: a group must always expose
     * max(min_selection, 1) effective-active options, even an optional one.
     */
    private function guardOutletModifierSatisfiable(
        OutletProduct $assignment,
        ProductModifierGroup $group,
        ProductModifier $modifier,
    ): ?Result {
        if ($modifier->status !== CatalogStatus::Active || ! $this->isGroupEffectiveActive($assignment, $group)) {
            return null;
        }

        $required = ModifierSelectionRule::requiredActiveCount($group->min_selection);

        return $this->effectiveActiveModifierCount($assignment, $group, $modifier->id) < $required
            ? $this->outletModifierRequiredByActiveGroup()
            : null;
    }

    /**
     * Clearing a group override makes the group effective-active again, so its
     * options must satisfy the group invariant *after* the clear. Options may
     * have been hidden while the group itself was hidden, so this can fail.
     */
    private function assertOutletGroupSatisfiable(
        OutletProduct $assignment,
        ProductModifierGroup $group,
    ): ?Result {
        if ($group->status !== CatalogStatus::Active) {
            return null;
        }

        $required = ModifierSelectionRule::requiredActiveCount($group->min_selection);

        return $this->effectiveActiveModifierCount($assignment, $group) < $required
            ? $this->outletModifierGroupUnsatisfiable()
            : null;
    }

    /**
     * Whether the product currently counts as sellable at this outlet, ignoring
     * the variant count (that is the thing being guarded).
     */
    private function isSellableAtOutlet(OutletProduct $assignment, Product $product): bool
    {
        return $assignment->status === CatalogStatus::Active
            && $assignment->availability_status === ProductAvailabilityStatus::Available
            && $product->status === CatalogStatus::Active
            && $this->categoryStatusOf($product) === CatalogStatus::Active;
    }

    private function categoryStatusOf(Product $product): ?CatalogStatus
    {
        if (! $product->relationLoaded('category')) {
            $product->loadMissing('category');
        }

        return $product->category?->status;
    }

    /**
     * The overrides recorded for this outlet and product, keyed by item id.
     *
     * @return array<string, CatalogStatus>
     */
    private function overrideMap(HasMany $relation, string $productId, string $itemColumn): array
    {
        $rows = $relation
            ->where('product_id', $productId)
            ->get([$itemColumn, 'status']);

        $map = [];

        foreach ($rows as $row) {
            /** @var Model $row */
            $status = $row->status;
            $map[(string) $row->{$itemColumn}] = $status instanceof CatalogStatus
                ? $status
                : CatalogStatus::from((string) $status);
        }

        return $map;
    }

    /**
     * @return array<string, CatalogStatus>
     */
    private function variantOverrideMap(OutletProduct $assignment, string $productId): array
    {
        return $this->overrideMap($assignment->variantOverrides(), $productId, 'product_variant_id');
    }

    /**
     * @return array<string, CatalogStatus>
     */
    private function groupOverrideMap(OutletProduct $assignment, string $productId): array
    {
        return $this->overrideMap($assignment->modifierGroupOverrides(), $productId, 'product_modifier_group_id');
    }

    /**
     * @return array<string, CatalogStatus>
     */
    private function modifierOverrideMap(OutletProduct $assignment, string $productId): array
    {
        return $this->overrideMap($assignment->modifierOverrides(), $productId, 'product_modifier_id');
    }

    /**
     * Variants that are active at this outlet, i.e. active in the master and not
     * hidden by an override.
     */
    private function effectiveActiveVariantCount(
        OutletProduct $assignment,
        Product $product,
        ?string $ignoreVariantId = null,
    ): int {
        $overrides = $this->variantOverrideMap($assignment, $product->id);

        return ProductVariant::query()
            ->where('product_id', $product->id)
            ->when($ignoreVariantId !== null, fn ($query) => $query->whereKeyNot($ignoreVariantId))
            ->get(['id', 'status'])
            ->filter(fn (ProductVariant $variant): bool => OutletItemStatus::isEffectiveActive(
                $variant->status,
                $overrides[$variant->id] ?? null,
            ))
            ->count();
    }

    /**
     * Options of one group that are active at this outlet.
     */
    private function effectiveActiveModifierCount(
        OutletProduct $assignment,
        ProductModifierGroup $group,
        ?string $ignoreModifierId = null,
    ): int {
        $overrides = $this->modifierOverrideMap($assignment, $group->product_id);

        return ProductModifier::query()
            ->where('modifier_group_id', $group->id)
            ->when($ignoreModifierId !== null, fn ($query) => $query->whereKeyNot($ignoreModifierId))
            ->get(['id', 'status'])
            ->filter(fn (ProductModifier $modifier): bool => OutletItemStatus::isEffectiveActive(
                $modifier->status,
                $overrides[$modifier->id] ?? null,
            ))
            ->count();
    }

    private function isGroupEffectiveActive(OutletProduct $assignment, ProductModifierGroup $group): bool
    {
        $overrides = $this->groupOverrideMap($assignment, $group->product_id);

        return OutletItemStatus::isEffectiveActive($group->status, $overrides[$group->id] ?? null);
    }

    /**
     * Write (or refresh) the restriction for one item at one outlet.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $extra
     * @return TModel
     */
    private function recordOverride(
        string $modelClass,
        OutletProduct $assignment,
        string $itemColumn,
        string $itemId,
        array $extra = [],
        ?string $actorId = null,
    ): Model {
        /** @var TModel $model */
        $model = $modelClass::query()->firstOrNew([
            'outlet_id' => $assignment->outlet_id,
            $itemColumn => $itemId,
        ]);

        $model->fill([
            'merchant_id' => $assignment->merchant_id,
            'product_id' => $assignment->product_id,
            'status' => CatalogStatus::Inactive,
            'deactivated_by' => $actorId,
            'deactivated_at' => now(),
            ...$extra,
        ]);
        $model->save();

        return $model;
    }

    private function recordVariantOverride(
        OutletProduct $assignment,
        ProductVariant $variant,
        ?string $actorId = null,
    ): OutletProductVariant {
        /** @var OutletProductVariant $model */
        $model = $this->recordOverride(
            OutletProductVariant::class,
            $assignment,
            'product_variant_id',
            $variant->id,
            actorId: $actorId,
        );

        return $model;
    }

    private function recordModifierGroupOverride(
        OutletProduct $assignment,
        ProductModifierGroup $group,
        ?string $actorId = null,
    ): OutletProductModifierGroup {
        /** @var OutletProductModifierGroup $model */
        $model = $this->recordOverride(
            OutletProductModifierGroup::class,
            $assignment,
            'product_modifier_group_id',
            $group->id,
            actorId: $actorId,
        );

        return $model;
    }

    private function recordModifierOverride(
        OutletProduct $assignment,
        ProductModifier $modifier,
        ?string $actorId = null,
    ): OutletProductModifier {
        /** @var OutletProductModifier $model */
        $model = $this->recordOverride(
            OutletProductModifier::class,
            $assignment,
            'product_modifier_id',
            $modifier->id,
            ['product_modifier_group_id' => $modifier->modifier_group_id],
            $actorId,
        );

        return $model;
    }

    /**
     * Drop the restriction so the item follows the master catalog again. A
     * soft delete keeps the write history while the partial unique index frees
     * the pair for a future override.
     */
    private function clearOverride(string $modelClass, string $itemColumn, string $itemId): int
    {
        /** @var class-string<Model> $modelClass */
        return $modelClass::query()->where($itemColumn, $itemId)->delete();
    }
}
