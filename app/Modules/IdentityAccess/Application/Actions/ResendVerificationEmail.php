<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\Log;

final class ResendVerificationEmail
{
    public function __construct(private readonly EmailVerification $emailVerification) {}

    public function __invoke(string $email): Result
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $this->emailVerification->send($user->id);

            Log::info('verification_resend', ['user_id' => $user->id]);
        }

        return Result::ok(null);
    }
}
