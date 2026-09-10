<?php

namespace App\Shared\Exceptions;

use Throwable;

final class ConflictException extends ApiException
{
    public function __construct(string $message = 'The request conflicts with the current state of the resource.', ?Throwable $previous = null)
    {
        parent::__construct($message, 409, 'conflict', previous: $previous);
    }
}
