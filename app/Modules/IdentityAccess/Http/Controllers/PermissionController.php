<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Modules\IdentityAccess\Application\Actions\CreatePermission;
use App\Modules\IdentityAccess\Application\Actions\DeletePermission;
use App\Modules\IdentityAccess\Application\Actions\UpdatePermission;
use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Modules\IdentityAccess\Http\Requests\StorePermissionRequest;
use App\Modules\IdentityAccess\Http\Requests\UpdatePermissionRequest;
use App\Modules\IdentityAccess\Http\Resources\PermissionResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PermissionController extends Controller
{
    public function __construct(
        private readonly CreatePermission $createPermission,
        private readonly UpdatePermission $updatePermission,
        private readonly DeletePermission $deletePermission,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Permission::query()->with('roles')->orderBy('name');

        if (filled($search = $validated['search'] ?? null)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, PermissionResource::collection($paginator->items()));
    }

    public function show(Permission $permission): JsonResponse
    {
        return ApiResponse::success(new PermissionResource($permission->load('roles')));
    }

    #[OpenApiResponse(201, 'Permission created', type: 'array{data: \App\Modules\IdentityAccess\Http\Resources\PermissionResource}')]
    #[IgnoreResponse(200)]
    public function store(StorePermissionRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->createPermission)($request->validated()),
            fn (Permission $permission) => ApiResponse::created(
                new PermissionResource($permission),
                route('api.v1.permissions.show', $permission),
            ),
        );
    }

    #[OpenApiResponse(200, 'Permission updated', type: 'array{data: \App\Modules\IdentityAccess\Http\Resources\PermissionResource}')]
    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->updatePermission)($permission, $request->validated()),
            fn (Permission $permission) => ApiResponse::success(new PermissionResource($permission)),
        );
    }

    #[OpenApiResponse(204, 'Permission deleted')]
    #[IgnoreResponse(200)]
    public function destroy(Permission $permission): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->deletePermission)($permission),
            fn () => ApiResponse::noContent(),
        );
    }
}
