<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Modules\IdentityAccess\Domain\Models\User;

interface UserProvisioning
{
    /**
     * Provision a new identity user.
     *
     * @param  array{name: string, email: string, phone?: string|null, password: string}  $attributes
     */
    public function create(array $attributes): User;
}
