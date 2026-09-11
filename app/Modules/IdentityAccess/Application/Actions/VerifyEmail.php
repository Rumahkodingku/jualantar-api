<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Application\Concerns\ReportsAuthenticationErrors;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\Log;

final class VerifyEmail
{
    use ReportsAuthenticationErrors;

    /**
     * Validate the verification hash for the target user and mark their email
     * as verified. The operation is idempotent for already-verified users.
     */
    public function __invoke(string $id, string $hash): Result
    {
        $user = User::find($id);

        if ($user === null) {
            return $this->verificationFailed($id);
        }

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->verificationFailed($id);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Log::info('email_verification_success', ['user_id' => $user->id]);

        return Result::ok($user);
    }

    private function verificationFailed(string $id): Result
    {
        Log::warning('email_verification_failed', ['user_id' => $id]);

        return $this->invalidVerification();
    }
}
