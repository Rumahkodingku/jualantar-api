<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Application\Catalog\Services\CatalogQueryService;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Http\Catalog\Requests\IndexOutletCatalogRequest;
use App\Modules\Merchant\Http\Catalog\Requests\ReorderOutletCatalogRequest;
use App\Modules\Merchant\Http\Resources\OutletCatalogItemResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OutletCatalogController extends Controller
{
    private const RESOURCE = '\App\Modules\Merchant\Http\Resources\OutletCatalogItemResource';

    private const PAGINATED = 'array{data: array<'.self::RESOURCE.'>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly CatalogQueryService $query,
    ) {}

    #[OpenApiResponse(200, 'Effective outlet catalog', type: self::PAGINATED)]
    public function index(IndexOutletCatalogRequest $request, string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->outletCatalog($outlet, 'merchant.operations.catalog.view'),
            fn (MerchantOutlet $model) => ApiResponse::paginated(
                $paginator = $this->query->outletCatalog($model, $request->validated()),
                OutletCatalogItemResource::collection($paginator->items()),
            ),
        );
    }

    #[OpenApiResponse(204, 'Outlet catalog reordered')]
    #[IgnoreResponse(200)]
    public function reorder(ReorderOutletCatalogRequest $request, string $outlet): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->outletCatalog($outlet, 'merchant.operations.catalog.order.update'),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                $this->query->reorderOutletCatalog($model, $request->validated()['items']),
                fn () => ApiResponse::noContent(),
            ),
        );
    }
}
