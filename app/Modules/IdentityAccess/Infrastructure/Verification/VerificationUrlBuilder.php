<?php

namespace App\Modules\IdentityAccess\Infrastructure\Verification;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Builds the email-verification URL for a user.
 *
 * IdentityAccess owns token/signature generation; Communications only
 * transports the resulting URL (see the Communications PRD, link handling).
 */
final class VerificationUrlBuilder
{
    public function forUser(User $user): string
    {
        $apiUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        if (! $user->hasRole('merchant')) {
            return $apiUrl;
        }

        $frontendBase = rtrim((string) config('merchant.app_url'), '/');

        if ($frontendBase === '') {
            return $apiUrl;
        }

        parse_str((string) parse_url($apiUrl, PHP_URL_QUERY), $query);

        return $frontendBase.'/merchant/verify-email?'.http_build_query([
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
            'expires' => $query['expires'] ?? null,
            'signature' => $query['signature'] ?? null,
        ]);
    }
}
