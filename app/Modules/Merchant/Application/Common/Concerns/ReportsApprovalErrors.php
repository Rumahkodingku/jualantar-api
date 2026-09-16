<?php

namespace App\Modules\Merchant\Application\Common\Concerns;

use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsApprovalErrors
{
    private function invalidStateTransition(
        string $message = 'The application is not in a state that allows this transition.',
    ): Result {
        return Result::err(new ResultError(
            code: 'invalid_state_transition',
            message: $message,
            status: 409,
            title: 'Conflict',
        ));
    }

    private function notAssigned(): Result
    {
        return Result::err(new ResultError(
            code: 'not_assigned',
            message: 'Only the assigned reviewer can perform this action.',
            status: 403,
            title: 'Forbidden',
        ));
    }

    /**
     * @param  array<string, array<int, string>>  $fields
     */
    private function businessRuleViolation(string $message, array $fields = []): Result
    {
        return Result::err(new ResultError(
            code: 'business_rule_violation',
            message: $message,
            status: 422,
            title: 'Unprocessable Entity',
            fields: $fields,
        ));
    }

    private function approvalNotFound(): Result
    {
        return Result::err(new ResultError(
            code: 'merchant_approval_not_found',
            message: 'The merchant approval was not found.',
            status: 404,
            title: 'Not Found',
        ));
    }

    private function applicationNotFound(): Result
    {
        return Result::err(new ResultError(
            code: 'merchant_application_not_found',
            message: 'The merchant application was not found.',
            status: 404,
            title: 'Not Found',
        ));
    }
}
