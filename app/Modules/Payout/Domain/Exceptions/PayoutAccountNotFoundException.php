<?php

namespace App\Modules\Payout\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class PayoutAccountNotFoundException extends ApiException
{
    public function __construct(string $message = 'The payout account was not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
