<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;

/**
 * Read-only access to user identity for other modules.
 *
 * Other modules must never import the IdentityAccess domain models or the
 * RBAC package directly; they depend on this contract instead.
 */
interface UserLookup
{
    public function user(string $userId): UserData;

    /**
     * @param  list<string>  $userIds
     * @return array<string, UserData> keyed by user id
     */
    public function usersByIds(array $userIds): array;
}
