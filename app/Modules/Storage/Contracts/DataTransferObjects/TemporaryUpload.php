<?php

namespace App\Modules\Storage\Contracts\DataTransferObjects;

use DateTimeInterface;

final readonly class TemporaryUpload
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $url,
        public array $headers,
        public string $path,
        public DateTimeInterface $expiresAt,
    ) {}
}
