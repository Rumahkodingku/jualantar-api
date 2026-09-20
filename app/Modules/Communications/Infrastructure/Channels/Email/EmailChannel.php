<?php

namespace App\Modules\Communications\Infrastructure\Channels\Email;

use App\Modules\Communications\Contracts\Channels\Channel;
use App\Modules\Communications\Contracts\Channels\ProviderSendResult;
use App\Modules\Communications\Contracts\Channels\RenderedMessage;
use App\Modules\Communications\Contracts\Enums\CommunicationChannel;

final class EmailChannel implements Channel
{
    public function __construct(private readonly EmailProvider $provider) {}

    public function supports(CommunicationChannel $channel): bool
    {
        return $channel === CommunicationChannel::Email;
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    public function send(RenderedMessage $message): ProviderSendResult
    {
        return $this->provider->send(new EmailMessage(
            to: $message->recipientAddress,
            subject: $message->subject ?? '',
            html: $message->html ?? $message->text,
            text: $message->text,
        ));
    }
}
