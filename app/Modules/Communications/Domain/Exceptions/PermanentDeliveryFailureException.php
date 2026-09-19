<?php

namespace App\Modules\Communications\Domain\Exceptions;

use Throwable;

/**
 * Signals that a provider failure must not be retried automatically.
 */
final class PermanentDeliveryFailureException extends CommunicationException
{
    public function __construct(
        string $message = 'The provider permanently rejected the communication.',
        string $problemCode = 'communication_provider_rejected',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 422, $problemCode, $previous);
    }
}
