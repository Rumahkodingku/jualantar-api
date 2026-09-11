<?php

namespace App\Modules\IdentityAccess\Domain\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * The system super administrator role.
     */
    public const SUPER_ADMIN = 'super-admin';

    /**
     * System roles that must never be renamed or deleted through the API.
     *
     * @var list<string>
     */
    public const PROTECTED = [self::SUPER_ADMIN];

    /**
     * The guard used for all JualAntar RBAC roles.
     */
    protected string $guard_name = 'sanctum';

    public static function isProtected(string $name): bool
    {
        return in_array($name, self::PROTECTED, true);
    }
}
