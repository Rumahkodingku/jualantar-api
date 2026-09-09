<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

abstract class ApiException extends HttpException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        int $status,
        private readonly string $problemCode,
        array $headers = [],
        private readonly array $errors = [],
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($status, $message, $previous, $headers, 0);
    }

    public function problemCode(): string
    {
        return $this->problemCode;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
