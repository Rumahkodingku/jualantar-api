<?php

namespace App\Modules\Service\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class ServiceNotFoundException extends ApiException
{
    public function __construct(string $message = 'The service was not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
