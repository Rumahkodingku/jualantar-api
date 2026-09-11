<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Application\Concerns\ReportsRbacErrors;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Shared\Result\Result;

final class UpdateRole
{
    use ReportsRbacErrors;

    /**
     * @param  array{name: string}  $data
     */
    public function __invoke(Role $role, array $data): Result
    {
        $name = $data['name'];

        if ($name !== $role->name && (Role::isProtected($role->name) || Role::isProtected($name))) {
            return $this->protectedRole($name);
        }

        $role->update(['name' => $name]);

        return Result::ok($role->refresh());
    }
}
