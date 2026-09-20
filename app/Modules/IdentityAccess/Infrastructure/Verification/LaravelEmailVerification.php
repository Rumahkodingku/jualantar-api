<?php

namespace App\Modules\IdentityAccess\Infrastructure\Verification;

use App\Modules\Communications\Contracts\Communications;
use App\Modules\Communications\Contracts\DataTransferObjects\SendCommunicationData;
use App\Modules\Communications\Contracts\Enums\CommunicationChannel;
use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Domain\Exceptions\UserNotFoundException;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LaravelEmailVerification implements EmailVerification
{
    public function __construct(
        private readonly Communications $communications,
        private readonly VerificationUrlBuilder $urlBuilder,
    ) {}

    public function send(string $userId): void
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            throw new UserNotFoundException;
        }

        $verificationUrl = $this->urlBuilder->forUser($user);

        try {
            $this->communications->send(new SendCommunicationData(
                channel: CommunicationChannel::Email,
                type: 'identity.email_verification',
                recipientAddress: $user->email,
                subject: 'Verifikasi email Anda',
                template: 'email.identity.email-verification',
                payload: [
                    'name' => $user->email,
                    'verification_url' => $verificationUrl,
                ],
                idempotencyKey: null,
            ));
        } catch (Throwable $e) {
            Log::warning('email_verification_dispatch_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
