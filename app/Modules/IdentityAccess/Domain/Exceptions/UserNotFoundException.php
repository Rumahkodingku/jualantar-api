<?php

namespace App\Modules\IdentityAccess\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class UserNotFoundException extends ApiException
{
    public function __construct(string $message = 'The user was not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
