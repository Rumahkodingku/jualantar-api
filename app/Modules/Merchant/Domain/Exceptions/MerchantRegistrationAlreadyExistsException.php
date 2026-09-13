<?php

namespace App\Modules\Merchant\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class MerchantRegistrationAlreadyExistsException extends ApiException
{
    public function __construct(string $message = 'An active merchant registration already exists for this user.')
    {
        parent::__construct($message, 409, 'merchant_registration_already_exists');
    }
}
