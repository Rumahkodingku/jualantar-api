<?php

namespace App\Modules\Communications\Contracts\DataTransferObjects;

use App\Modules\Communications\Contracts\Enums\CommunicationChannel;

final readonly class SendCommunicationData
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public CommunicationChannel $channel,
        public string $type,
        public string $recipientAddress,
        public ?string $subject = null,
        public ?string $template = null,
        public ?array $payload = null,
        public ?string $idempotencyKey = null,
    ) {}
}
