<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Application\Concerns\ReportsRbacErrors;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Shared\Result\Result;
use Spatie\Permission\Exceptions\RoleAlreadyExists;

final class CreateRole
{
    use ReportsRbacErrors;

    /**
     * @param  array{name: string}  $data
     */
    public function __invoke(array $data): Result
    {
        if (Role::isProtected($data['name'])) {
            return $this->protectedRole($data['name']);
        }

        try {
            return Result::ok(Role::create(['name' => $data['name']]));
        } catch (RoleAlreadyExists) {
            return $this->roleAlreadyExists($data['name']);
        }
    }
}
