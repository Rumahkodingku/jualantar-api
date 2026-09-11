<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Shared\Result\Result;

final class Logout
{
    public function __invoke(User $user): Result
    {
        $user->currentAccessToken()?->delete();

        return Result::ok(null);
    }
}
