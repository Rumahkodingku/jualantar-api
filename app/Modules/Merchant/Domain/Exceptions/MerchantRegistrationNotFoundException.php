<?php

namespace App\Modules\Merchant\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class MerchantRegistrationNotFoundException extends ApiException
{
    public function __construct(string $message = 'The merchant registration was not found.')
    {
        parent::__construct($message, 404, 'merchant_registration_not_found');
    }
}
