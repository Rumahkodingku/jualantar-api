<?php

namespace App\Modules\IdentityAccess\Contracts;

/**
 * Extension point for business modules to enrich the user context returned by
 * /auth/me and login. IdentityAccess is a generic module and must not depend on
 * business modules, so the dependency points the other way: a module that owns
 * extra user context implements this contract and binds it in its provider.
 */
interface UserContextContributor
{
    /**
     * Extra keys merged into the user resource payload.
     *
     * @return array<string, mixed>
     */
    public function contribute(int|string $userId): array;
}
