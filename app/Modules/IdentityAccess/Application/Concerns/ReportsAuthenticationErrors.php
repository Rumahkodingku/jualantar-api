<?php

namespace App\Modules\IdentityAccess\Application\Concerns;

use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsAuthenticationErrors
{
    private function invalidVerification(): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_verification',
            message: 'The email verification link is invalid or has expired.',
            status: 403,
            title: 'Forbidden',
        ));
    }

    private function invalidCredentials(): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_credentials',
            message: 'The provided credentials are incorrect.',
            status: 401,
            title: 'Unauthenticated',
        ));
    }

    private function emailNotVerified(): Result
    {
        return Result::err(new ResultError(
            code: 'email_not_verified',
            message: 'Your email address has not been verified.',
            status: 403,
            title: 'Forbidden',
        ));
    }
}
