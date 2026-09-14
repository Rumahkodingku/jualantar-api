<?php

namespace App\Modules\IdentityAccess\Infrastructure\Verification;

use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Domain\Exceptions\UserNotFoundException;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Notifications\MerchantVerifyEmailNotification;

final class LaravelEmailVerification implements EmailVerification
{
    public function send(string $userId): void
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            throw new UserNotFoundException;
        }

        if ($user->hasRole('merchant')) {
            $user->notify(new MerchantVerifyEmailNotification);

            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
