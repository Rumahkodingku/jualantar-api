<?php

namespace App\Modules\Communications\Infrastructure\Channels\Email;

final readonly class EmailMessage
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $html,
        public string $text,
    ) {}
}
