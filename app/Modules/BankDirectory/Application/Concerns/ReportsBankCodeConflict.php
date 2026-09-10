<?php

namespace App\Modules\BankDirectory\Application\Concerns;

use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsBankCodeConflict
{
    private function codeConflict(): Result
    {
        return Result::err(new ResultError(
            code: 'conflict',
            message: 'A bank with the same code already exists.',
            status: 409,
            title: 'Conflict',
        ));
    }
}
