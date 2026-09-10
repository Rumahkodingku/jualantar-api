<?php

namespace App\Shared\Exceptions;

use Throwable;

final class NotFoundException extends ApiException
{
    public function __construct(string $message = 'The requested resource was not found.', ?Throwable $previous = null)
    {
        parent::__construct($message, 404, 'not_found', previous: $previous);
    }
}
