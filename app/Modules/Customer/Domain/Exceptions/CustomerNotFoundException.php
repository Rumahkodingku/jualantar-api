<?php

namespace App\Modules\Customer\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class CustomerNotFoundException extends ApiException
{
    public function __construct(string $message = 'The customer was not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
