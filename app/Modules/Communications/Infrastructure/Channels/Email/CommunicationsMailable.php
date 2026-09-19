<?php

namespace App\Modules\Communications\Infrastructure\Channels\Email;

use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

/**
 * Renders a pre-rendered HTML body and a plain-text fallback.
 *
 * Laravel's Mailable has no "text string" API, so the text part is attached
 * through the Symfony message callback.
 */
final class CommunicationsMailable extends Mailable
{
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $htmlBody,
        public readonly string $textBody,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine)
            ->html($this->htmlBody)
            ->withSymfonyMessage(function (Email $message): void {
                $message->text($this->textBody);
            });
    }
}
