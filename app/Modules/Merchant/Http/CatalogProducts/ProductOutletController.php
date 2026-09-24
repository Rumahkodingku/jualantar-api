<?php

namespace App\Modules\Merchant\Http\CatalogProducts;

use App\Modules\Merchant\Application\Catalog\Actions\ActivateProductOutletAssignment;
use App\Modules\Merchant\Application\Catalog\Actions\AssignProductOutlets;
use App\Modules\Merchant\Application\Catalog\Actions\DeactivateProductOutletAssignment;
use App\Modules\Merchant\Application\Catalog\Actions\RemoveProductOutletAssignment;
use App\Modules\Merchant\Application\Catalog\Actions\ReplaceProductOutlets;
use App\Modules\Merchant\Application\Catalog\Actions\UpdateOutletAvailability;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Http\CatalogProducts\Requests\IndexProductOutletRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\ReplaceProductOutletRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\StoreProductOutletRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\UpdateOutletAvailabilityRequest;
use App\Modules\Merchant\Http\Resources\OutletProductAssignmentResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductOutletController extends Controller
{
    private const RESOURCE = '\App\Modules\Merchant\Http\Resources\OutletProductAssignmentResource';

    private const PAGINATED = 'array{data: array<'.self::RESOURCE.'>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}';

    private const ASSIGNMENT_STATUS_CAPABILITY = 'merchant.operations.catalog.assignment.status.update';

    private const AVAILABILITY_CAPABILITY = 'merchant.operations.catalog.availability.update';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly AssignProductOutlets $assignOutlets,
        private readonly ReplaceProductOutlets $replaceOutlets,
        private readonly RemoveProductOutletAssignment $removeAssignment,
        private readonly ActivateProductOutletAssignment $activateAssignment,
        private readonly DeactivateProductOutletAssignment $deactivateAssignment,
        private readonly UpdateOutletAvailability $updateAvailability,
    ) {}

    #[OpenApiResponse(200, 'Product outlet assignments', type: self::PAGINATED)]
    public function index(IndexProductOutletRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => $this->list($model, $request->validated()),
        );
    }

    #[OpenApiResponse(201, 'Outlets assigned', type: 'array{data: array<'.self::RESOURCE.'>}')]
    #[IgnoreResponse(200)]
    public function store(StoreProductOutletRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                $this->authorization->productForMerchant($merchant, $product),
                fn (Product $model) => ApiResponse::fromResult(
                    ($this->assignOutlets)($model, $request->validated()['outlet_ids']),
                    fn () => $this->assignments($model, created: true),
                ),
            ),
        );
    }

    #[OpenApiResponse(200, 'Outlets replaced', type: 'array{data: array<'.self::RESOURCE.'>}')]
    public function replace(ReplaceProductOutletRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                $this->authorization->productForMerchant($merchant, $product),
                fn (Product $model) => ApiResponse::fromResult(
                    ($this->replaceOutlets)($model, $request->validated()['outlet_ids']),
                    fn () => $this->assignments($model),
                ),
            ),
        );
    }

    #[OpenApiResponse(204, 'Assignment removed')]
    #[IgnoreResponse(200)]
    public function destroy(string $product, string $outlet): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->assignmentForOwner($product, $outlet),
            fn (OutletProduct $assignment) => ApiResponse::fromResult(
                ($this->removeAssignment)($assignment),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    #[OpenApiResponse(200, 'Assignment activated', type: 'array{data: '.self::RESOURCE.'}')]
    public function activate(string $product, string $outlet): JsonResponse
    {
        return $this->actOnAssignment(
            $outlet,
            $product,
            self::ASSIGNMENT_STATUS_CAPABILITY,
            fn (OutletProduct $assignment): Result => ($this->activateAssignment)($assignment),
        );
    }

    #[OpenApiResponse(200, 'Assignment deactivated', type: 'array{data: '.self::RESOURCE.'}')]
    public function deactivate(string $product, string $outlet): JsonResponse
    {
        return $this->actOnAssignment(
            $outlet,
            $product,
            self::ASSIGNMENT_STATUS_CAPABILITY,
            fn (OutletProduct $assignment): Result => ($this->deactivateAssignment)($assignment),
        );
    }

    #[OpenApiResponse(200, 'Availability updated', type: 'array{data: '.self::RESOURCE.'}')]
    public function availability(UpdateOutletAvailabilityRequest $request, string $product, string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->assignmentForOutletAction($outlet, $product, self::AVAILABILITY_CAPABILITY),
            fn (OutletProduct $assignment) => ApiResponse::fromResult(
                ($this->updateAvailability)($assignment, $request->validated()),
                fn (OutletProduct $updated) => ApiResponse::success(new OutletProductAssignmentResource($updated)),
            ),
        );
    }

    /**
     * @param  callable(OutletProduct): Result  $action
     */
    private function actOnAssignment(
        string $outlet,
        string $product,
        string $capability,
        callable $action,
    ): JsonResponse {
        return ApiResponse::fromResult(
            $this->authorization->assignmentForOutletAction($outlet, $product, $capability),
            fn (OutletProduct $assignment) => ApiResponse::fromResult(
                $action($assignment),
                fn (OutletProduct $updated) => ApiResponse::success(new OutletProductAssignmentResource($updated)),
            ),
        );
    }

    private function assignments(Product $product, bool $created = false): JsonResponse
    {
        $assignments = OutletProduct::query()
            ->where('product_id', $product->id)
            ->with('outlet')
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $resource = OutletProductAssignmentResource::collection($assignments);

        return $created
            ? ApiResponse::created($resource)
            : ApiResponse::success($resource);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function list(Product $product, array $validated): JsonResponse
    {
        $query = OutletProduct::query()
            ->where('product_id', $product->id)
            ->with('outlet');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['availability'])) {
            $query->where('availability_status', $validated['availability']);
        }

        $query->orderBy($validated['sort'] ?? 'display_order', $validated['order'] ?? 'asc')
            ->orderBy('created_at')
            ->orderBy('id');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, OutletProductAssignmentResource::collection($paginator->items()));
    }
}
