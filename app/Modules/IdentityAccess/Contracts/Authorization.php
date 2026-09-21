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

    /**
     * Grant a role to an existing user. Unknown users or roles are ignored so
     * callers never need to know the RBAC implementation details.
     */
    public function assignRole(int|string $userId, string $role): void;

    /**
     * Revoke a role from an existing user when the user currently holds it.
     */
    public function removeRole(int|string $userId, string $role): void;
}
