<?php

namespace App\Modules\Service\Http\Controllers;

use App\Modules\Service\Application\Actions\DeactivateServiceCategory;
use App\Modules\Service\Application\Actions\StoreServiceCategory;
use App\Modules\Service\Application\Actions\UpdateServiceCategory;
use App\Modules\Service\Domain\Exceptions\ServiceNotFoundException;
use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use App\Modules\Service\Http\Requests\StoreServiceCategoryRequest;
use App\Modules\Service\Http\Requests\UpdateServiceCategoryRequest;
use App\Modules\Service\Http\Resources\PublicServiceCategoryResource;
use App\Modules\Service\Http\Resources\ServiceCategoryResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ServiceCategoryController extends Controller
{
    public function __construct(
        private readonly StoreServiceCategory $storeServiceCategory,
        private readonly UpdateServiceCategory $updateServiceCategory,
        private readonly DeactivateServiceCategory $deactivateServiceCategory,
    ) {}

    public function index(Request $request, Service $service): JsonResponse
    {
        if (! $this->canManage($request) && ! $service->is_active) {
            throw new ServiceNotFoundException;
        }

        if ($this->canManage($request)) {
            return ApiResponse::success(
                ServiceCategoryResource::collection(
                    $service->categories()->orderBy('name')->get(),
                ),
            );
        }

        return ApiResponse::success(
            PublicServiceCategoryResource::collection(
                $service->categories()->active()->orderBy('name')->get(),
            ),
        );
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'is_active' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'updated_at'])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ServiceCategory::query();

        if (filled($search = $validated['search'] ?? null)) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if (isset($validated['service_id'])) {
            $query->where('service_id', $validated['service_id']);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy($validated['sort'] ?? 'name', $validated['order'] ?? 'asc');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, ServiceCategoryResource::collection($paginator->items()));
    }

    public function show(ServiceCategory $category): JsonResponse
    {
        return ApiResponse::success(new ServiceCategoryResource($category));
    }

    #[OpenApiResponse(201, 'Category created', type: 'array{data: \App\Modules\Service\Http\Resources\ServiceCategoryResource}')]
    #[IgnoreResponse(200)]
    public function store(StoreServiceCategoryRequest $request, Service $service): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->storeServiceCategory)($service, $request->validated()),
            fn(ServiceCategory $category) => ApiResponse::created(
                new ServiceCategoryResource($category),
                route('api.v1.categories.show', $category),
            ),
        );
    }

    #[OpenApiResponse(200, 'Category updated', type: 'array{data: \App\Modules\Service\Http\Resources\ServiceCategoryResource}')]
    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $category): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->updateServiceCategory)($category, $request->validated()),
            fn(ServiceCategory $category) => ApiResponse::success(new ServiceCategoryResource($category)),
        );
    }

    #[OpenApiResponse(204, 'Category deactivated')]
    #[IgnoreResponse(200)]
    public function destroy(ServiceCategory $category): Response
    {
        ($this->deactivateServiceCategory)($category);

        return ApiResponse::noContent();
    }

    private function canManage(Request $request): bool
    {
        return (bool) $request->user('sanctum')?->can('categories.manage');
    }
}
