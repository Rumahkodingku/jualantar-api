<?php

namespace App\Modules\Service\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class ServiceCategoryNotFoundException extends ApiException
{
    public function __construct(string $message = 'The category was not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
