<?php

namespace App\Modules\Merchant\Application\Concerns;

use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsRegistrationErrors
{
    /**
     * Guard mutations so they only run while the registration is a draft.
     */
    private function requireDraft(Merchant $merchant): ?Result
    {
        if ($merchant->status !== MerchantStatus::Draft) {
            return $this->invalidRegistrationState(
                'The merchant registration can only be modified while in draft.',
            );
        }

        return null;
    }

    private function alreadyExists(): Result
    {
        return Result::err(new ResultError(
            code: 'merchant_registration_already_exists',
            message: 'An active merchant registration already exists for this user.',
            status: 409,
            title: 'Conflict',
        ));
    }

    private function invalidRegistrationState(
        string $message = 'The merchant registration is not in a modifiable state.',
    ): Result {
        return Result::err(new ResultError(
            code: 'invalid_registration_state',
            message: $message,
            status: 409,
            title: 'Conflict',
        ));
    }

    private function invalidService(): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_service',
            message: 'The selected service is invalid or inactive.',
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }

    private function invalidCategory(string $message = 'One or more selected categories are invalid.'): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_category',
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

    private function legalEntityConflict(): Result
    {
        return Result::err(new ResultError(
            code: 'conflict',
            message: 'A legal entity with the given NIB or NPWP already exists.',
            status: 409,
            title: 'Conflict',
        ));
    }

    private function uploadInvalid(string $message = 'The upload metadata is invalid.'): Result
    {
        return Result::err(new ResultError(
            code: 'upload_invalid',
            message: $message,
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }

    private function registrationNotFound(): Result
    {
        return Result::err(new ResultError(
            code: 'merchant_registration_not_found',
            message: 'The merchant registration was not found.',
            status: 404,
            title: 'Not Found',
        ));
    }

    /**
     * @param  list<string>  $fields
     */
    private function registrationIncomplete(array $fields): Result
    {
        $errors = [];

        foreach ($fields as $field) {
            $errors[$field] = ['The '.str_replace('_', ' ', $field).' is required before submitting.'];
        }

        return Result::err(new ResultError(
            code: 'registration_incomplete',
            message: 'The merchant registration is incomplete.',
            status: 422,
            title: 'Unprocessable Entity',
            fields: $errors,
        ));
    }
}
