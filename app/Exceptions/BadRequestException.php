<?php

namespace App\Exceptions;

use Throwable;

final class BadRequestException extends ApiException
{
    public function __construct(string $message = 'The request is invalid.', ?Throwable $previous = null)
    {
        parent::__construct($message, 400, 'bad_request', previous: $previous);
    }
}
