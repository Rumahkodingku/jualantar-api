<?php

namespace App\Modules\IdentityAccess\Infrastructure\Users;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use App\Modules\IdentityAccess\Contracts\UserProvisioning;
use App\Modules\IdentityAccess\Domain\Models\User;

final class EloquentUserProvisioning implements UserProvisioning
{
    /**
     * @param  array{email: string, phone?: string|null, password: string}  $attributes
     */
    public function provisionCustomer(array $attributes): UserData
    {
        $user = User::create($attributes);
        $user->assignRole('customer');

        return new UserData(
            id: $user->id,
            email: $user->email,
            phone: $user->phone,
            emailVerified: $user->hasVerifiedEmail(),
        );
    }
}
