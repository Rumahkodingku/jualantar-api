<?php

namespace App\Modules\Service\Http\Controllers;

use App\Modules\Service\Application\Actions\DeactivateService;
use App\Modules\Service\Application\Actions\StoreService;
use App\Modules\Service\Application\Actions\UpdateService;
use App\Modules\Service\Domain\Exceptions\ServiceNotFoundException;
use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Http\Requests\StoreServiceRequest;
use App\Modules\Service\Http\Requests\UpdateServiceRequest;
use App\Modules\Service\Http\Resources\PublicServiceResource;
use App\Modules\Service\Http\Resources\ServiceResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function __construct(
        private readonly StoreService $storeService,
        private readonly UpdateService $updateService,
        private readonly DeactivateService $deactivateService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($this->canManage($request)) {
            return $this->adminIndex($request);
        }

        return ApiResponse::success(
            PublicServiceResource::collection(
                Service::query()->active()->orderBy('name')->get(),
            ),
        );
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        if (! $this->canManage($request) && ! $service->is_active) {
            throw new ServiceNotFoundException;
        }

        return ApiResponse::success(
            $this->canManage($request)
                ? new ServiceResource($service)
                : new PublicServiceResource($service),
        );
    }

    #[OpenApiResponse(201, 'Service created', type: 'array{data: \App\Modules\Service\Http\Resources\ServiceResource}')]
    #[IgnoreResponse(200)]
    public function store(StoreServiceRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->storeService)($request->validated()),
            fn (Service $service) => ApiResponse::created(
                new ServiceResource($service),
                route('api.v1.services.show', $service),
            ),
        );
    }

    #[OpenApiResponse(200, 'Service updated', type: 'array{data: \App\Modules\Service\Http\Resources\ServiceResource}')]
    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->updateService)($service, $request->validated()),
            fn (Service $service) => ApiResponse::success(new ServiceResource($service)),
        );
    }

    #[OpenApiResponse(204, 'Service deactivated')]
    #[IgnoreResponse(200)]
    public function destroy(Service $service): Response
    {
        ($this->deactivateService)($service);

        return ApiResponse::noContent();
    }

    private function canManage(Request $request): bool
    {
        return (bool) $request->user('sanctum')?->can('services.manage');
    }

    private function adminIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'updated_at'])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Service::query();

        if (filled($search = $validated['search'] ?? null)) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy($validated['sort'] ?? 'name', $validated['order'] ?? 'asc');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, ServiceResource::collection($paginator->items()));
    }
}
