<?php

namespace App\Modules\Customer\Application\Concerns;

use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsRegistrationErrors
{
    private function duplicateRegistration(): Result
    {
        return Result::err(new ResultError(
            code: 'validation_error',
            message: 'An account with the given email, phone, or username already exists.',
            status: 422,
            title: 'Validation Failed',
        ));
    }
}
