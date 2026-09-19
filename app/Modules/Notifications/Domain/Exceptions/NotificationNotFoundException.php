<?php

namespace App\Modules\Notifications\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;

final class NotificationNotFoundException extends ApiException
{
    public function __construct(string $message = 'The notification was not found.')
    {
        parent::__construct($message, 404, 'notification_not_found');
    }
}
