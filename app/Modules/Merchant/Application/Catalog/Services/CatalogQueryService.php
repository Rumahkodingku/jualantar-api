<?php

namespace App\Modules\Merchant\Application\Catalog\Services;

use App\Modules\Merchant\Application\Catalog\Concerns\ReportsCatalogErrors;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Shared\Result\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the effective outlet catalog: every product assigned to an
 * outlet, including assignments that are inactive or unavailable so the
 * merchant can manage them. Queries are built here so the controller stays
 * thin; response envelopes stay in the controller.
 */
final class CatalogQueryService
{
    use ReportsCatalogErrors;

    public function __construct(
        private readonly MediaUrlHydrator $mediaUrls,
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
            ->with([
                'category',
                'variants',
                'media',
                'outletProducts' => fn ($relation) => $relation->where('outlet_id', $outlet->id),
            ]);

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
        }

        return $paginator;
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
