<?php

namespace App\Modules\IdentityAccess\Http\Resources;

use App\Modules\IdentityAccess\Contracts\UserContextContributor;
use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->roles->pluck('name')->values()->all(),
            'permissions' => $this->resolvedPermissions(),
            ...app(UserContextContributor::class)->contribute($this->id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve the permissions a user effectively holds. Super-admins are granted
     * every ability through the Gate::before hook, so they report the full
     * permission catalog instead of only their explicitly assigned permissions.
     *
     * @return list<string>
     */
    private function resolvedPermissions(): array
    {
        if ($this->hasRole(Role::SUPER_ADMIN)) {
            return Permission::query()->orderBy('name')->pluck('name')->values()->all();
        }

        return $this->getAllPermissions()->pluck('name')->values()->all();
    }
}
