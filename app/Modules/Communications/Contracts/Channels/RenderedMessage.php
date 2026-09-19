<?php

namespace App\Modules\Communications\Contracts\Channels;

use App\Modules\Communications\Domain\Enums\CommunicationChannel;

/**
 * Channel-neutral, rendered outbound message.
 *
 * The renderer produces this from a Communication; channels translate it into
 * their provider-specific message format. It never exposes the Eloquent model.
 */
final readonly class RenderedMessage
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public CommunicationChannel $channel,
        public string $recipientAddress,
        public ?string $subject,
        public ?string $html,
        public string $text,
        public ?array $metadata = null,
    ) {}
}
