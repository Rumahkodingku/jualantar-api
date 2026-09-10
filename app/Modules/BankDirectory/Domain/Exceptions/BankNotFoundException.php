<?php

namespace App\Modules\BankDirectory\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class BankNotFoundException extends ApiException
{
    public function __construct(string $message = 'The bank was not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
