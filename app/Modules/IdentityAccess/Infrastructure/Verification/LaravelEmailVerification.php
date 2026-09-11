<?php

namespace App\Modules\IdentityAccess\Infrastructure\Verification;

use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Domain\Models\User;

final class LaravelEmailVerification implements EmailVerification
{
    public function send(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }
}
