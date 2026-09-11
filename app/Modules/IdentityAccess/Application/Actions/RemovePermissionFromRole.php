<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Application\Concerns\ReportsRbacErrors;
use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Shared\Result\Result;

final class RemovePermissionFromRole
{
    use ReportsRbacErrors;

    /**
     * @param  list<string>  $permissionNames
     */
    public function __invoke(Role $role, array $permissionNames): Result
    {
        $permissionNames = array_values(array_unique($permissionNames));

        $existing = Permission::query()
            ->where('guard_name', 'sanctum')
            ->whereIn('name', $permissionNames)
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff($permissionNames, $existing));

        if ($missing !== []) {
            return $this->invalidPermission($missing);
        }

        $role->revokePermissionTo($permissionNames);

        return Result::ok($role->refresh()->load('permissions'));
    }
}
