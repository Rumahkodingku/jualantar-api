<?php

namespace App\Modules\Communications\Contracts\Channels;

use DateTimeImmutable;

/**
 * Normalized result of a provider submission.
 *
 * Provider adapters translate their native responses and exceptions into this
 * shape so the delivery job never depends on provider internals.
 */
final readonly class ProviderSendResult
{
    public function __construct(
        public bool $accepted,
        public string $provider,
        public ?string $providerMessageId = null,
        public ?DateTimeImmutable $acceptedAt = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $permanent = false,
    ) {}

    public static function accepted(
        string $provider,
        ?string $providerMessageId = null,
        ?DateTimeImmutable $acceptedAt = null,
    ): self {
        return new self(
            accepted: true,
            provider: $provider,
            providerMessageId: $providerMessageId,
            acceptedAt: $acceptedAt,
        );
    }

    public static function failed(
        string $provider,
        string $errorCode,
        string $errorMessage,
        bool $permanent = false,
    ): self {
        return new self(
            accepted: false,
            provider: $provider,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            permanent: $permanent,
        );
    }
}
