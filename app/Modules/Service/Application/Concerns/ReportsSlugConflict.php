<?php

namespace App\Modules\Service\Application\Concerns;

use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsSlugConflict
{
    private function slugConflict(): Result
    {
        return Result::err(new ResultError(
            code: 'unprocessable_entity',
            message: 'The slug has already been used.',
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }
}
