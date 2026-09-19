<?php

namespace App\Modules\Communications\Contracts\DataTransferObjects;

use App\Modules\Communications\Domain\Enums\CommunicationStatus;
use DateTimeImmutable;

final readonly class CommunicationResult
{
    public function __construct(
        public string $communicationId,
        public CommunicationStatus $status,
        public bool $queued,
        public ?string $providerMessageId = null,
        public ?DateTimeImmutable $acceptedAt = null,
    ) {}

    /**
     * Provider-neutral, safe-to-log representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'communication_id' => $this->communicationId,
            'status' => $this->status->value,
            'queued' => $this->queued,
            'provider_message_id' => $this->providerMessageId,
            'accepted_at' => $this->acceptedAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
