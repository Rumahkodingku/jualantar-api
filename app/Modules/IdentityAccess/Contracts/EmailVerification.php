<?php

namespace App\Modules\IdentityAccess\Contracts;

interface EmailVerification
{
    /**
     * Send the email verification notification to an unverified user.
     */
    public function send(string $userId): void;
}
