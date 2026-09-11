<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Shared\Result\Result;

final class UpdatePermission
{
    /**
     * @param  array{name: string}  $data
     */
    public function __invoke(Permission $permission, array $data): Result
    {
        $permission->update(['name' => $data['name']]);

        return Result::ok($permission->refresh());
    }
}
