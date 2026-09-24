<?php

namespace App\Modules\Merchant\Http\CatalogProducts;

use App\Modules\Merchant\Application\Catalog\Actions\ActivateCategory;
use App\Modules\Merchant\Application\Catalog\Actions\CreateCategory;
use App\Modules\Merchant\Application\Catalog\Actions\DeactivateCategory;
use App\Modules\Merchant\Application\Catalog\Actions\DeleteCategory;
use App\Modules\Merchant\Application\Catalog\Actions\ReorderCategories;
use App\Modules\Merchant\Application\Catalog\Actions\UpdateCategory;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Http\CatalogProducts\Requests\IndexCategoryRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\ReorderCategoryRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\StoreCategoryRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\UpdateCategoryRequest;
use App\Modules\Merchant\Http\Resources\CatalogCategoryResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    private const RESOURCE = '\App\Modules\Merchant\Http\Resources\CatalogCategoryResource';

    private const PAGINATED = 'array{data: array<'.self::RESOURCE.'>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly CreateCategory $createCategory,
        private readonly UpdateCategory $updateCategory,
        private readonly DeleteCategory $deleteCategory,
        private readonly ActivateCategory $activateCategory,
        private readonly DeactivateCategory $deactivateCategory,
        private readonly ReorderCategories $reorderCategories,
    ) {}

    #[OpenApiResponse(200, 'Categories', type: self::PAGINATED)]
    public function index(IndexCategoryRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => $this->list($merchant, $request->validated()),
        );
    }

    #[OpenApiResponse(201, 'Category created', type: 'array{data: '.self::RESOURCE.'}')]
    #[IgnoreResponse(200)]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->createCategory)($merchant, $request->validated()),
                fn (CatalogCategory $category) => ApiResponse::created(
                    new CatalogCategoryResource($category),
                    route('api.v1.merchant.catalog.categories.show', ['category' => $category->id]),
                ),
            ),
        );
    }

    #[OpenApiResponse(200, 'Category', type: 'array{data: '.self::RESOURCE.'}')]
    public function show(string $category): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->category($category),
            fn (CatalogCategory $model) => ApiResponse::success(new CatalogCategoryResource($model)),
        );
    }

    #[OpenApiResponse(200, 'Category updated', type: 'array{data: '.self::RESOURCE.'}')]
    public function update(UpdateCategoryRequest $request, string $category): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->category($category),
            fn (CatalogCategory $model) => ApiResponse::fromResult(
                ($this->updateCategory)($model, $request->validated()),
                fn (CatalogCategory $updated) => ApiResponse::success(new CatalogCategoryResource($updated)),
            ),
        );
    }

    #[OpenApiResponse(204, 'Category deleted')]
    #[IgnoreResponse(200)]
    public function destroy(string $category): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->category($category),
            fn (CatalogCategory $model) => ApiResponse::fromResult(
                ($this->deleteCategory)($model),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    #[OpenApiResponse(200, 'Category activated', type: 'array{data: '.self::RESOURCE.'}')]
    public function activate(string $category): JsonResponse
    {
        return $this->changeStatus($category, fn (CatalogCategory $model): Result => ($this->activateCategory)($model));
    }

    #[OpenApiResponse(200, 'Category deactivated', type: 'array{data: '.self::RESOURCE.'}')]
    public function deactivate(string $category): JsonResponse
    {
        return $this->changeStatus($category, fn (CatalogCategory $model): Result => ($this->deactivateCategory)($model));
    }

    #[OpenApiResponse(204, 'Categories reordered')]
    #[IgnoreResponse(200)]
    public function reorder(ReorderCategoryRequest $request): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->reorderCategories)($merchant, $request->validated()['items']),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    /**
     * @param  callable(CatalogCategory): Result  $action
     */
    private function changeStatus(string $category, callable $action): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->category($category),
            fn (CatalogCategory $model) => ApiResponse::fromResult(
                $action($model),
                fn (CatalogCategory $updated) => ApiResponse::success(new CatalogCategoryResource($updated)),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function list(Merchant $merchant, array $validated): JsonResponse
    {
        $query = CatalogCategory::query()->where('merchant_id', $merchant->id);

        if (filled($search = $validated['search'] ?? null)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->orderBy($validated['sort'] ?? 'display_order', $validated['order'] ?? 'asc')
            ->orderBy('created_at')
            ->orderBy('id');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, CatalogCategoryResource::collection($paginator->items()));
    }
}
