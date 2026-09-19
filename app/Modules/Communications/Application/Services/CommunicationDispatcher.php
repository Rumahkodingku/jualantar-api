<?php

namespace App\Modules\Communications\Application\Services;

use App\Modules\Communications\Contracts\Channels\Channel;
use App\Modules\Communications\Contracts\Channels\ProviderSendResult;
use App\Modules\Communications\Domain\Enums\CommunicationChannel;
use App\Modules\Communications\Domain\Exceptions\CommunicationException;
use App\Modules\Communications\Domain\Models\Communication;

/**
 * Resolves the channel for a communication, renders it, and submits it.
 *
 * New channels are registered in the constructor map; the public contract and
 * delivery job never reference concrete channel classes.
 */
final class CommunicationDispatcher
{
    /**
     * @param  array<string, Channel>  $channels  keyed by CommunicationChannel value
     */
    public function __construct(
        private readonly CommunicationRenderer $renderer,
        private readonly array $channels,
    ) {}

    public function providerName(Communication $communication): string
    {
        return $this->channelFor($communication->channel)->providerName();
    }

    public function dispatch(Communication $communication): ProviderSendResult
    {
        $channel = $this->channelFor($communication->channel);

        return $channel->send($this->renderer->render($communication));
    }

    private function channelFor(CommunicationChannel $channel): Channel
    {
        foreach ($this->channels as $candidate) {
            if ($candidate->supports($channel)) {
                return $candidate;
            }
        }

        throw CommunicationException::configurationError(
            "No channel is registered for [{$channel->value}].",
        );
    }
}
