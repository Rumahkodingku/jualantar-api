<?php

namespace App\Modules\Communications\Contracts\Channels;

use App\Modules\Communications\Domain\Enums\CommunicationChannel;

/**
 * Channel SPI. New channels (SMS, WhatsApp) implement this interface and are
 * registered in the CommunicationDispatcher without changing the public
 * Communications contract.
 */
interface Channel
{
    public function supports(CommunicationChannel $channel): bool;

    /**
     * Provider identifier recorded on delivery attempts (e.g. "smtp").
     */
    public function providerName(): string;

    public function send(RenderedMessage $message): ProviderSendResult;
}
