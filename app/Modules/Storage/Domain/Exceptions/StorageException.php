<?php

namespace App\Modules\Storage\Domain\Exceptions;

use App\Shared\Exceptions\ApiException;
use Throwable;

class StorageException extends ApiException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'The storage operation failed.',
        string $problemCode = 'storage_error',
        int $status = 500,
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $problemCode, context: $context, previous: $previous);
    }

    public static function fromThrowable(Throwable $e, string $path, string $disk): self
    {
        return new self(
            context: ['path' => $path, 'disk' => $disk],
            previous: $e,
        );
    }
}
