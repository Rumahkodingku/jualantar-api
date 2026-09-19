<?php

namespace App\Modules\Communications\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;
use Throwable;

class CommunicationException extends ApiException
{
    public function __construct(
        string $message,
        int $status,
        string $problemCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $problemCode, previous: $previous);
    }

    public static function invalidRecipient(string $message = 'The recipient address is invalid.'): self
    {
        return new self($message, 422, 'communication_invalid_recipient');
    }

    public static function templateNotFound(string $template): self
    {
        return new self(
            "The communication template [{$template}] was not found.",
            422,
            'communication_template_not_found',
        );
    }

    public static function providerRejected(string $message = 'The provider rejected the communication.'): self
    {
        return new self($message, 422, 'communication_provider_rejected');
    }

    public static function providerUnavailable(string $message = 'The provider is temporarily unavailable.', ?Throwable $previous = null): self
    {
        return new self($message, 503, 'communication_provider_unavailable', $previous);
    }

    public static function configurationError(string $message = 'The communication provider is misconfigured.'): self
    {
        return new self($message, 500, 'communication_configuration_error');
    }

    public static function duplicate(string $message = 'A communication with this idempotency key already exists.'): self
    {
        return new self($message, 409, 'communication_duplicate');
    }

    public static function deliveryFailed(string $message = 'The communication could not be delivered.'): self
    {
        return new self($message, 502, 'communication_delivery_failed');
    }
}
