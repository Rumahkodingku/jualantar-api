<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Modules\IdentityAccess\Application\Actions\AssignPermissionToRole;
use App\Modules\IdentityAccess\Application\Actions\CreateRole;
use App\Modules\IdentityAccess\Application\Actions\DeleteRole;
use App\Modules\IdentityAccess\Application\Actions\RemovePermissionFromRole;
use App\Modules\IdentityAccess\Application\Actions\UpdateRole;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Http\Requests\AssignPermissionRequest;
use App\Modules\IdentityAccess\Http\Requests\StoreRoleRequest;
use App\Modules\IdentityAccess\Http\Requests\UpdateRoleRequest;
use App\Modules\IdentityAccess\Http\Resources\RoleResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly CreateRole $createRole,
        private readonly UpdateRole $updateRole,
        private readonly DeleteRole $deleteRole,
        private readonly AssignPermissionToRole $assignPermissionToRole,
        private readonly RemovePermissionFromRole $removePermissionFromRole,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Role::query()->with('permissions')->orderBy('name');

        if (filled($search = $validated['search'] ?? null)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, RoleResource::collection($paginator->items()));
    }

    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(new RoleResource($role->load('permissions')));
    }

    #[OpenApiResponse(201, 'Role created', type: 'array{data: \App\Modules\IdentityAccess\Http\Resources\RoleResource}')]
    #[IgnoreResponse(200)]
    public function store(StoreRoleRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->createRole)($request->validated()),
            fn (Role $role) => ApiResponse::created(
                new RoleResource($role),
                route('api.v1.roles.show', $role),
            ),
        );
    }

    #[OpenApiResponse(200, 'Role updated', type: 'array{data: \App\Modules\IdentityAccess\Http\Resources\RoleResource}')]
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->updateRole)($role, $request->validated()),
            fn (Role $role) => ApiResponse::success(new RoleResource($role)),
        );
    }

    #[OpenApiResponse(204, 'Role deleted')]
    #[IgnoreResponse(200)]
    public function destroy(Role $role): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->deleteRole)($role),
            fn () => ApiResponse::noContent(),
        );
    }

    #[OpenApiResponse(200, 'Permissions assigned', type: 'array{data: \App\Modules\IdentityAccess\Http\Resources\RoleResource}')]
    public function assignPermissions(AssignPermissionRequest $request, Role $role): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->assignPermissionToRole)($role, $request->validated('permissions')),
            fn (Role $role) => ApiResponse::success(new RoleResource($role)),
        );
    }

    #[OpenApiResponse(200, 'Permissions removed', type: 'array{data: \App\Modules\IdentityAccess\Http\Resources\RoleResource}')]
    public function removePermissions(AssignPermissionRequest $request, Role $role): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->removePermissionFromRole)($role, $request->validated('permissions')),
            fn (Role $role) => ApiResponse::success(new RoleResource($role)),
        );
    }
}
