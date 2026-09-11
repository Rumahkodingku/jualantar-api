<?php

namespace App\Modules\IdentityAccess\Domain\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * The guard used for all JualAntar RBAC permissions.
     */
    protected string $guard_name = 'sanctum';
}
