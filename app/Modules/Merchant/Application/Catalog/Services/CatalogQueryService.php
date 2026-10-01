<?php

namespace App\Modules\Merchant\Application\Catalog\Services;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use App\Modules\IdentityAccess\Contracts\UserLookup;
use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Catalog\OutletItemStatus;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\OutletProductModifier;
use App\Modules\Merchant\Domain\Models\OutletProductModifierGroup;
use App\Modules\Merchant\Domain\Models\OutletProductVariant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Shared\Result\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the effective outlet catalog: every product assigned to an
 * outlet, including assignments that are inactive or unavailable so the
 * merchant can manage them. Queries are built here so the controller stays
 * thin; response envelopes stay in the controller.
 *
 * It also owns the two per-outlet status override projections: the effective
 * status of every item at one outlet, and — for the owner — the reverse list of
 * which outlets deviated from the master status.
 */
final class CatalogQueryService
{
    use ReportsCatalogErrors;

    public function __construct(
        private readonly MediaUrlHydrator $mediaUrls,
        private readonly UserLookup $users,
    ) {}

    /**
     * @param  array{search?: string|null, category_id?: string|null, status?: string|null, availability?: string|null, sort?: string|null, order?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function outletCatalog(MerchantOutlet $outlet, array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->select('merchant.products.*')
            ->join('merchant.outlet_products', function ($join) use ($outlet): void {
                $join->on('merchant.outlet_products.product_id', '=', 'merchant.products.id')
                    ->where('merchant.outlet_products.outlet_id', '=', $outlet->id)
                    ->whereNull('merchant.outlet_products.deleted_at');
            })
            ->join('merchant.catalog_categories', 'merchant.catalog_categories.id', '=', 'merchant.products.category_id')
            ->with($this->outletRelations($outlet->id));

        if (filled($search = $filters['search'] ?? null)) {
            $query->where('merchant.products.name', 'ilike', "%{$search}%");
        }

        if (isset($filters['category_id'])) {
            $query->where('merchant.products.category_id', $filters['category_id']);
        }

        if (isset($filters['status'])) {
            $query->where('merchant.outlet_products.status', $filters['status']);
        }

        if (isset($filters['availability'])) {
            $query->where('merchant.outlet_products.availability_status', $filters['availability']);
        }

        $this->applyOrdering($query, $filters);

        $paginator = $query->paginate($filters['per_page'] ?? 15)->withQueryString();

        foreach ($paginator->items() as $product) {
            $this->mediaUrls->hydrate($product->media);
            $this->keepOnlyEffectiveItems($product);
        }

        return $paginator;
    }

    /**
     * The single outlet catalog row for a product that is assigned to the
     * outlet. It reuses the relations of the list but loads *every* status, so
     * an outlet manager can see the items that are hidden here and turn them
     * back on; the list itself stays filtered for browsing.
     */
    public function outletProductDetail(Product $product, string $outletId): Product
    {
        $product->load($this->outletRelations($outletId, includeInactive: true));
        $this->mediaUrls->hydrate($product->media);

        return $product;
    }

    /**
     * Relations shared by the outlet catalog list and detail. The assignment
     * relation is filtered to the target outlet so rows never carry another
     * outlet's state.
     *
     * `includeInactive` un-hides the customization items. The per-outlet status
     * overrides are always loaded: they decide the effective status even when
     * the master row is already active.
     *
     * @return array<string, mixed>
     */
    private function outletRelations(string $outletId, bool $includeInactive = false): array
    {
        $customization = fn ($relation) => $includeInactive
            ? $relation
            : $relation->where('status', CatalogStatus::Active->value);

        return [
            'category',
            'variants',
            'media',
            'modifierGroups' => fn ($relation) => $customization($relation)
                ->orderBy('display_order')
                ->orderBy('created_at')
                ->orderBy('id'),
            'modifierGroups.modifiers' => fn ($relation) => $customization($relation)
                ->orderBy('display_order')
                ->orderBy('created_at')
                ->orderBy('id'),
            'outletProducts' => fn ($relation) => $relation->where('outlet_id', $outletId),
            'outletVariantOverrides' => fn ($relation) => $relation->where('outlet_id', $outletId),
            'outletModifierGroupOverrides' => fn ($relation) => $relation->where('outlet_id', $outletId),
            'outletModifierOverrides' => fn ($relation) => $relation->where('outlet_id', $outletId),
        ];
    }

    /**
     * Drop the items this outlet has hidden from the loaded relations.
     *
     * The list is the effective catalog — what this outlet actually offers — so
     * an item hidden by an override must not show up as available. The detail
     * endpoint keeps them instead, because that is where they get managed.
     */
    private function keepOnlyEffectiveItems(Product $product): void
    {
        if ($product->relationLoaded('variants')) {
            $hidden = $this->statusMap($product->outletVariantOverrides, 'product_variant_id');

            $product->setRelation('variants', $product->variants
                ->filter(fn (ProductVariant $variant): bool => OutletItemStatus::isEffectiveActive(
                    $variant->status,
                    $hidden[$variant->id] ?? null,
                ))
                ->values());
        }

        if (! $product->relationLoaded('modifierGroups')) {
            return;
        }

        $hiddenGroups = $this->statusMap($product->outletModifierGroupOverrides, 'product_modifier_group_id');
        $hiddenModifiers = $this->statusMap($product->outletModifierOverrides, 'product_modifier_id');

        $groups = $product->modifierGroups
            ->filter(fn (ProductModifierGroup $group): bool => OutletItemStatus::isEffectiveActive(
                $group->status,
                $hiddenGroups[$group->id] ?? null,
            ))
            ->map(function (ProductModifierGroup $group) use ($hiddenModifiers): ProductModifierGroup {
                if ($group->relationLoaded('modifiers')) {
                    $group->setRelation('modifiers', $group->modifiers
                        ->filter(fn (ProductModifier $modifier): bool => OutletItemStatus::isEffectiveActive(
                            $modifier->status,
                            $hiddenModifiers[$modifier->id] ?? null,
                        ))
                        ->values());
                }

                return $group;
            })
            ->values();

        $product->setRelation('modifierGroups', $groups);
    }

    /**
     * @param  EloquentCollection<int, Model>  $overrides
     * @return array<string, CatalogStatus>
     */
    private function statusMap(EloquentCollection $overrides, string $itemColumn): array
    {
        return $overrides
            ->filter(fn (Model $override): bool => $override->status instanceof CatalogStatus)
            ->mapWithKeys(fn (Model $override): array => [
                (string) $override->{$itemColumn} => $override->status,
            ])
            ->all();
    }

    /**
     * Attach the per-outlet overrides of every item of a product so the owner can
     * see which outlets deviated from the master status, and who did it.
     *
     * The relations are attached by hand rather than through a nested
     * `load()`: the master detail already eager loaded the items with their
     * display ordering, and re-loading them would drop that ordering. Three
     * grouped queries, and only when there is something to attach.
     */
    public function loadOutletOverrideViews(Product $product): void
    {
        $variants = $product->relationLoaded('variants') ? $product->variants : collect();
        $groups = $product->relationLoaded('modifierGroups') ? $product->modifierGroups : collect();

        $modifierIds = $groups
            ->flatMap(fn (ProductModifierGroup $group): array => $group->relationLoaded('modifiers')
                ? $group->modifiers->modelKeys()
                : [])
            ->all();

        $variantOverrides = $this->groupedOverrides(
            OutletProductVariant::class,
            'product_variant_id',
            $variants->modelKeys(),
        );

        foreach ($variants as $variant) {
            $this->hydrateOverrideActors($variantOverrides->get($variant->id, collect()));
            $variant->setRelation('outletStatusOverrides', $variantOverrides->get($variant->id, collect()));
        }

        $groupOverrides = $this->groupedOverrides(
            OutletProductModifierGroup::class,
            'product_modifier_group_id',
            $groups->modelKeys(),
        );

        $modifierOverrides = $this->groupedOverrides(
            OutletProductModifier::class,
            'product_modifier_id',
            $modifierIds,
        );

        foreach ($groups as $group) {
            $groupItems = $groupOverrides->get($group->id, collect());
            $this->hydrateOverrideActors($groupItems);
            $group->setRelation('outletStatusOverrides', $groupItems);

            if (! $group->relationLoaded('modifiers')) {
                continue;
            }

            foreach ($group->modifiers as $modifier) {
                $optionItems = $modifierOverrides->get($modifier->id, collect());
                $this->hydrateOverrideActors($optionItems);
                $modifier->setRelation('outletStatusOverrides', $optionItems);
            }
        }
    }

    /**
     * One grouped query per item kind, keyed by the master item id.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  list<string>  $itemIds
     * @return Collection<string, Collection<int, TModel>>
     */
    private function groupedOverrides(string $modelClass, string $itemColumn, array $itemIds): Collection
    {
        if ($itemIds === []) {
            return collect();
        }

        return $modelClass::query()
            ->whereIn($itemColumn, $itemIds)
            ->with('outlet')
            ->get()
            ->groupBy($itemColumn);
    }

    /**
     * Sort the overrides by outlet name and attach the outlet label plus the
     * actor's email. The actor is resolved through the IdentityAccess contract:
     * the merchant module never touches the user table directly.
     *
     * @param  Collection<int, Model>  $overrides
     */
    private function hydrateOverrideActors(Collection $overrides): void
    {
        $overrides->sortBy(fn (Model $override): string => (string) $override->outlet?->name)->values();

        $users = $this->resolveActors($overrides);

        foreach ($overrides as $override) {
            $actorId = $override->deactivated_by === null ? null : (string) $override->deactivated_by;

            $override->setAttribute('outlet_name', $override->outlet?->name);
            $override->setAttribute('deactivated_by_email', $actorId === null ? null : $users[$actorId]?->email);
        }
    }

    /**
     * @param  Collection<int, Model>  $overrides
     * @return array<string, UserData>
     */
    private function resolveActors(Collection $overrides): array
    {
        $ids = $overrides
            ->map(fn (Model $override): ?string => $override->deactivated_by === null ? null : (string) $override->deactivated_by)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $ids === [] ? [] : $this->users->usersByIds($ids);
    }

    /**
     * Reorder the assignment display order inside one outlet. It never touches
     * the master products.display_order.
     *
     * @param  list<array{product_id: string, display_order: int}>  $items
     */
    public function reorderOutletCatalog(MerchantOutlet $outlet, array $items): Result
    {
        return DB::transaction(function () use ($outlet, $items): Result {
            $productIds = array_map(fn (array $item): string => $item['product_id'], $items);

            $assigned = OutletProduct::query()
                ->where('outlet_id', $outlet->id)
                ->whereIn('product_id', $productIds)
                ->count();

            if ($assigned !== count($productIds)) {
                return $this->catalogValidationError([
                    'items' => ['One or more products are not assigned to this outlet.'],
                ]);
            }

            foreach ($items as $item) {
                OutletProduct::query()
                    ->where('outlet_id', $outlet->id)
                    ->where('product_id', $item['product_id'])
                    ->update(['display_order' => $item['display_order']]);
            }

            return Result::ok(null);
        });
    }

    /**
     * Default order mirrors PRD Bagian 7.6: category order, then the outlet
     * display order, then creation time and id. An explicit `sort` overrides it.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyOrdering(Builder $query, array $filters): void
    {
        $order = $filters['order'] ?? 'asc';

        if (isset($filters['sort'])) {
            $column = match ($filters['sort']) {
                'name' => 'merchant.products.name',
                'created_at' => 'merchant.outlet_products.created_at',
                default => 'merchant.outlet_products.display_order',
            };

            $query->orderBy($column, $order);
        } else {
            $query->orderBy('merchant.catalog_categories.display_order')
                ->orderBy('merchant.outlet_products.display_order', $order);
        }

        $query->orderBy('merchant.outlet_products.created_at')
            ->orderBy('merchant.products.id');
    }
}
