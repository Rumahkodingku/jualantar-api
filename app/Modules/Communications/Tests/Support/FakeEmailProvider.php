<?php

namespace App\Modules\Communications\Tests\Support;

use App\Modules\Communications\Contracts\Channels\ProviderSendResult;
use App\Modules\Communications\Infrastructure\Channels\Email\EmailMessage;
use App\Modules\Communications\Infrastructure\Channels\Email\EmailProvider;

final class FakeEmailProvider implements EmailProvider
{
    /**
     * @var list<EmailMessage>
     */
    public array $sent = [];

    public function __construct(private readonly ProviderSendResult $result) {}

    public function name(): string
    {
        return 'fake';
    }

    public function send(EmailMessage $message): ProviderSendResult
    {
        $this->sent[] = $message;

        return $this->result;
    }
}
