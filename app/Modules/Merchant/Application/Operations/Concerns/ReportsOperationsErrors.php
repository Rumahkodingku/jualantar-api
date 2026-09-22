<?php

namespace App\Modules\Merchant\Application\Operations\Concerns;

use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsOperationsErrors
{
    private function invalidStatusTransition(MerchantStatus $from, MerchantStatus $to): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_status_transition',
            message: "A merchant cannot transition from {$from->value} to {$to->value}.",
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }

    private function forbidden(): Result
    {
        return Result::err(new ResultError(
            code: 'forbidden',
            message: 'You do not have the capability required for this action.',
            status: 403,
            title: 'Forbidden',
        ));
    }

    private function outletNotFound(): Result
    {
        return Result::err(new ResultError(
            code: 'outlet_not_found',
            message: 'The outlet was not found in your scope.',
            status: 404,
            title: 'Not Found',
        ));
    }

    private function duplicateAssignment(): Result
    {
        return Result::err(new ResultError(
            code: 'duplicate_outlet_assignment',
            message: 'The user is already assigned to this outlet.',
            status: 409,
            title: 'Conflict',
        ));
    }

    private function userNotFound(): Result
    {
        return Result::err(new ResultError(
            code: 'user_not_found',
            message: 'The identity user was not found.',
            status: 404,
            title: 'Not Found',
        ));
    }

    private function assignmentNotFound(): Result
    {
        return Result::err(new ResultError(
            code: 'not_found',
            message: 'The outlet assignment was not found.',
            status: 404,
            title: 'Not Found',
        ));
    }

    private function businessRuleViolation(string $message): Result
    {
        return Result::err(new ResultError(
            code: 'business_rule_violation',
            message: $message,
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }

    private function uploadInvalid(string $message): Result
    {
        return Result::err(new ResultError(
            code: 'upload_invalid',
            message: $message,
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }

    private function invalidGeography(): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_geography',
            message: 'The selected village is invalid or inactive.',
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }
}
