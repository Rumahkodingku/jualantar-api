<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Application\Concerns\ReportsRbacErrors;
use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Shared\Result\Result;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;

final class CreatePermission
{
    use ReportsRbacErrors;

    /**
     * @param  array{name: string}  $data
     */
    public function __invoke(array $data): Result
    {
        try {
            return Result::ok(Permission::create(['name' => $data['name']]));
        } catch (PermissionAlreadyExists) {
            return $this->permissionAlreadyExists($data['name']);
        }
    }
}
