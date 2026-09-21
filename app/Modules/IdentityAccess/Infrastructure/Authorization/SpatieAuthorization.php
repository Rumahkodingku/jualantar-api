<?php

namespace App\Modules\IdentityAccess\Infrastructure\Authorization;

use App\Modules\IdentityAccess\Contracts\Authorization;
use App\Modules\IdentityAccess\Domain\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

final class SpatieAuthorization implements Authorization
{
    public function userHasRole(int|string $userId, string $role): bool
    {
        $user = User::find($userId);

        if ($user === null) {
            return false;
        }

        try {
            return $user->hasRole($role);
        } catch (RoleDoesNotExist) {
            return false;
        }
    }

    public function userHasPermission(int|string $userId, string $permission): bool
    {
        $user = User::find($userId);

        if ($user === null) {
            return false;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function assignRole(int|string $userId, string $role): void
    {
        $user = User::find($userId);

        if ($user === null) {
            return;
        }

        try {
            $user->assignRole($role);
        } catch (RoleDoesNotExist) {
            // An unknown role is a configuration error, not a caller error.
        }
    }

    public function removeRole(int|string $userId, string $role): void
    {
        $user = User::find($userId);

        if ($user === null) {
            return;
        }

        try {
            if ($user->hasRole($role)) {
                $user->removeRole($role);
            }
        } catch (RoleDoesNotExist) {
            // Nothing to revoke when the role is not configured.
        }
    }
}
