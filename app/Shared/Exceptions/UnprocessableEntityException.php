<?php

namespace App\Shared\Exceptions;

use Throwable;

final class UnprocessableEntityException extends ApiException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message = 'The request could not be processed.',
        string $code = 'unprocessable_entity',
        array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 422, $code, errors: $errors, previous: $previous);
    }
}
