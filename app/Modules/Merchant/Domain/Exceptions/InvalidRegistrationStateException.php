<?php

namespace App\Modules\Merchant\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class InvalidRegistrationStateException extends ApiException
{
    public function __construct(string $message = 'The merchant registration is not in a modifiable state.')
    {
        parent::__construct($message, 409, 'invalid_registration_state');
    }
}
