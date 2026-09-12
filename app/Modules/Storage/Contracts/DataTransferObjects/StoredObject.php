<?php

namespace App\Modules\Storage\Contracts\DataTransferObjects;

final readonly class StoredObject
{
    public function __construct(
        public string $path,
        public string $disk,
        public int $size,
        public string $mimeType,
        public ?string $etag = null,
    ) {}
}
