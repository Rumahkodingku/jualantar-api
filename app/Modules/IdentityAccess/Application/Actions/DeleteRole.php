<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Application\Concerns\ReportsRbacErrors;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Shared\Result\Result;

final class DeleteRole
{
    use ReportsRbacErrors;

    public function __invoke(Role $role): Result
    {
        if (Role::isProtected($role->name)) {
            return $this->protectedRole($role->name);
        }

        if ($role->users()->exists()) {
            return $this->roleInUse($role->name);
        }

        $role->delete();

        return Result::ok(null);
    }
}
