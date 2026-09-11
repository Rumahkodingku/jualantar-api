<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Application\Concerns\ReportsRbacErrors;
use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Shared\Result\Result;

final class DeletePermission
{
    use ReportsRbacErrors;

    public function __invoke(Permission $permission): Result
    {
        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return $this->permissionInUse($permission->name);
        }

        $permission->delete();

        return Result::ok(null);
    }
}
