<?php

namespace App\Modules\Geography\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class VillageNotFoundException extends ApiException
{
    public function __construct(string $message = 'The village was not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
