<?php

namespace App\Modules\Communications\Infrastructure\Channels\Email;

use App\Modules\Communications\Contracts\Channels\ProviderSendResult;

/**
 * Email transport SPI. Implementations talk to an external mail transport and
 * return a normalized result; framework mail types stay inside Infrastructure.
 */
interface EmailProvider
{
    /**
     * Provider identifier recorded on delivery attempts (e.g. "smtp").
     */
    public function name(): string;

    public function send(EmailMessage $message): ProviderSendResult;
}
