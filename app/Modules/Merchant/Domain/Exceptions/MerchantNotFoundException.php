<?php

namespace App\Modules\Merchant\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class MerchantNotFoundException extends ApiException
{
    public function __construct(string $message = 'The merchant was not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
