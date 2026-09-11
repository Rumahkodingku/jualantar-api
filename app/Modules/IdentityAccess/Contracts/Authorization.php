<?php

namespace App\Modules\IdentityAccess\Contracts;

/**
 * Public authorization seam for other modules.
 *
 * Other modules must never import the RBAC domain models or the Spatie
 * package directly; they depend on this contract instead.
 */
interface Authorization
{
    public function userHasRole(int|string $userId, string $role): bool;

    public function userHasPermission(int|string $userId, string $permission): bool;
}
