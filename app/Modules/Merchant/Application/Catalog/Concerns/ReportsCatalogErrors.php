<?php

namespace App\Modules\Merchant\Application\Catalog\Concerns;

use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsCatalogErrors
{
    private function catalogNotFound(string $message = 'The requested catalog resource was not found.'): Result
    {
        return Result::err(new ResultError(
            code: 'not_found',
            message: $message,
            status: 404,
            title: 'Not Found',
        ));
    }

    private function catalogCategoryInUse(): Result
    {
        return Result::err(new ResultError(
            code: 'catalog_category_in_use',
            message: 'The category still has products and cannot be deleted.',
            status: 409,
            title: 'Conflict',
        ));
    }

    private function catalogInvalidCategory(): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_category',
            message: 'The selected category is invalid.',
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }

    private function variableProductRequiresActiveVariant(): Result
    {
        return Result::err(new ResultError(
            code: 'variable_product_requires_active_variant',
            message: 'A variable product needs at least one active variant before it can be activated.',
            status: 409,
            title: 'Conflict',
        ));
    }

    private function variantNotAllowedForSimpleProduct(): Result
    {
        return Result::err(new ResultError(
            code: 'variant_not_allowed_for_simple_product',
            message: 'A simple product cannot have variants.',
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }

    private function lastActiveVariant(): Result
    {
        return Result::err(new ResultError(
            code: 'last_active_variant',
            message: 'An active product must keep at least one active variant.',
            status: 409,
            title: 'Conflict',
        ));
    }

    private function mediaLimitReached(): Result
    {
        return Result::err(new ResultError(
            code: 'media_limit_reached',
            message: 'This product already has the maximum number of media.',
            status: 409,
            title: 'Conflict',
        ));
    }

    private function catalogUploadInvalid(string $message): Result
    {
        return Result::err(new ResultError(
            code: 'upload_invalid',
            message: $message,
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }

    private function duplicateOutletAssignment(): Result
    {
        return Result::err(new ResultError(
            code: 'duplicate_outlet_assignment',
            message: 'One or more of the selected outlets already have this product assigned.',
            status: 409,
            title: 'Conflict',
        ));
    }

    /**
     * @param  array<string, list<string>>  $fields
     */
    private function catalogValidationError(
        array $fields,
        string $message = 'The given data failed validation.',
    ): Result {
        return Result::err(new ResultError(
            code: 'validation_error',
            message: $message,
            status: 422,
            title: 'Validation Failed',
            fields: $fields,
        ));
    }
}
