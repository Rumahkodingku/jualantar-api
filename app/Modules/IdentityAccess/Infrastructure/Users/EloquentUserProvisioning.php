<?php

namespace App\Modules\IdentityAccess\Infrastructure\Users;

use App\Modules\IdentityAccess\Contracts\UserProvisioning;
use App\Modules\IdentityAccess\Domain\Models\User;

final class EloquentUserProvisioning implements UserProvisioning
{
    /**
     * @param  array{email: string, phone?: string|null, password: string}  $attributes
     */
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }
}
