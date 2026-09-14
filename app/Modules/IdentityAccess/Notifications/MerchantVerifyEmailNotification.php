<?php

namespace App\Modules\IdentityAccess\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class MerchantVerifyEmailNotification extends VerifyEmail
{
    /**
     * Build a verification link that lands on the merchant PWA while keeping
     * the API signature intact. The frontend forwards the id/hash plus the
     * expires/signature query params back to the signed API endpoint.
     */
    protected function verificationUrl($notifiable): string
    {
        $apiUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        $frontendBase = rtrim((string) config('merchant.app_url'), '/');

        if ($frontendBase === '') {
            return $apiUrl;
        }

        parse_str((string) parse_url($apiUrl, PHP_URL_QUERY), $query);

        return $frontendBase.'/merchant/verify-email?'.http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $query['expires'] ?? null,
            'signature' => $query['signature'] ?? null,
        ]);
    }
}
