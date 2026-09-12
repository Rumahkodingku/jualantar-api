<?php

namespace App\Modules\Storage\Domain\Exceptions;

use Throwable;

final class ObjectNotFoundException extends StorageException
{
    public function __construct(string $path, ?Throwable $previous = null)
    {
        parent::__construct(
            message: 'The requested object was not found.',
            problemCode: 'object_not_found',
            status: 404,
            context: ['path' => $path],
            previous: $previous,
        );
    }
}
