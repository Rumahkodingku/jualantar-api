<?php

namespace App\Support\Result;

use InvalidArgumentException;

final readonly class ResultError
{
    /**
     * @param  array<string, array<int, string>>  $fields
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly int $status = 422,
        public readonly string $title = 'Unprocessable Entity',
        public readonly array $fields = [],
        public readonly array $context = [],
    ) {
        if ($status < 400 || $status > 499) {
            throw new InvalidArgumentException('Result errors must use a 4xx HTTP status code.');
        }
    }
}
