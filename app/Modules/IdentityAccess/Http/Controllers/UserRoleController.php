<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Modules\IdentityAccess\Application\Actions\AssignRoleToUser;
use App\Modules\IdentityAccess\Application\Actions\RemoveRoleFromUser;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Http\Requests\AssignRoleRequest;
use App\Modules\IdentityAccess\Http\Resources\RoleResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    public function __construct(
        private readonly AssignRoleToUser $assignRoleToUser,
        private readonly RemoveRoleFromUser $removeRoleFromUser,
    ) {}

    public function index(User $user): JsonResponse
    {
        return ApiResponse::success(
            RoleResource::collection($user->roles()->orderBy('name')->get()),
        );
    }

    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->assignRoleToUser)($request->user(), $user, $request->validated('roles')),
            fn (User $user) => ApiResponse::success(RoleResource::collection($user->roles)),
        );
    }

    public function destroy(AssignRoleRequest $request, User $user): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->removeRoleFromUser)($request->user(), $user, $request->validated('roles')),
            fn (User $user) => ApiResponse::success(RoleResource::collection($user->roles)),
        );
    }
}
