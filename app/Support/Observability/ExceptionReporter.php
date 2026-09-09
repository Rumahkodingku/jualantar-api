<?php

namespace App\Support\Observability;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ExceptionReporter
{
    /**
     * Log unexpected exceptions with request context so they can be correlated
     * via the trace id. Returning false stops Laravel's default logging for the
     * exceptions this reporter handles, avoiding duplicate error records.
     */
    public function __invoke(Throwable $e): ?bool
    {
        if ($this->isExpected($e)) {
            return null;
        }

        Log::error($e->getMessage(), [
            'exception' => $e,
            'status' => $this->statusFor($e),
            'method' => request()?->method(),
            'path' => request()?->path(),
            'trace_id' => is_string(Context::get('trace_id')) ? (string) Context::get('trace_id') : null,
        ]);

        return false;
    }

    private function isExpected(Throwable $e): bool
    {
        if ($e instanceof ApiException || $e instanceof ValidationException) {
            return true;
        }

        return $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500;
    }

    private function statusFor(Throwable $e): int
    {
        return $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
    }
}
