<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Modules\IdentityAccess\Domain\Models\User;

interface EmailVerification
{
    /**
     * Send the email verification notification to an unverified user.
     */
    public function send(User $user): void;
}
