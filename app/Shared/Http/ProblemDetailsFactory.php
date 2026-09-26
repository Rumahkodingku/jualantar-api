<?php

namespace App\Shared\Http;

use App\Shared\Exceptions\ApiException;
use App\Shared\Result\ResultError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Throwable;

final class ProblemDetailsFactory
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly bool $debug,
    ) {}

    /**
     * Render any exception as an RFC 9457 problem response, or null to defer
     * to Laravel's default handling (used for non-API requests).
     */
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $e instanceof ApiException => $this->problem(
                status: $e->getStatusCode(),
                code: $e->problemCode(),
                detail: $e->getMessage(),
                request: $request,
                errors: $e->errors(),
                headers: $e->getHeaders(),
            ),
            $e instanceof ValidationException => $this->problem(
                status: $e->status,
                code: 'validation_error',
                detail: $e->getMessage(),
                request: $request,
                errors: $e->errors(),
            ),
            $e instanceof AuthenticationException => $this->problem(
                status: 401,
                code: 'unauthenticated',
                detail: $e->getMessage(),
                request: $request,
            ),
            $e instanceof NotFoundHttpException => $this->problem(
                status: 404,
                code: 'not_found',
                detail: 'The requested resource was not found.',
                request: $request,
                headers: $e->getHeaders(),
            ),
            $e instanceof TooManyRequestsHttpException => $this->problem(
                status: 429,
                code: 'rate_limited',
                detail: $e->getMessage(),
                request: $request,
                headers: $e->getHeaders(),
            ),
            $e instanceof MethodNotAllowedHttpException => $this->problem(
                status: 405,
                code: 'method_not_allowed',
                detail: $e->getMessage(),
                request: $request,
                headers: $e->getHeaders(),
            ),
            $e instanceof UnsupportedMediaTypeHttpException => $this->problem(
                status: 415,
                code: 'unsupported_media_type',
                detail: $e->getMessage(),
                request: $request,
            ),
            $e instanceof BadRequestHttpException => $this->problem(
                status: 400,
                code: 'bad_request',
                detail: $e->getMessage(),
                request: $request,
                headers: $e->getHeaders(),
            ),
            $e instanceof HttpExceptionInterface => $this->problem(
                status: $e->getStatusCode(),
                code: $this->slugForStatus($e->getStatusCode()),
                detail: $e->getMessage(),
                request: $request,
                headers: $e->getHeaders(),
            ),
            $this->debug => $this->problem(
                status: 500,
                code: 'internal_server_error',
                detail: 'An unexpected error occurred.',
                request: $request,
                extensions: $this->debugExtensions($e),
            ),
            default => $this->problem(
                status: 500,
                code: 'internal_server_error',
                detail: 'An unexpected error occurred.',
                request: $request,
            ),
        };
    }

    /**
     * Build a problem response from a business Result failure.
     *
     * Machine-readable context travels as a `context` extension member so a
     * client can act on a conflict (the current draft version, the conflicting
     * key) without parsing the human-readable detail.
     */
    public function problemFromResultError(ResultError $error, ?Request $request = null): JsonResponse
    {
        return $this->problem(
            status: $error->status,
            code: $error->code,
            title: $error->title,
            detail: $error->message,
            request: $request,
            errors: $error->fields,
            extensions: $error->context === [] ? [] : ['context' => $error->context],
        );
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string>  $headers
     */
    private function problem(
        int $status,
        string $code,
        ?string $title = null,
        ?string $detail = null,
        ?Request $request = null,
        array $errors = [],
        array $extensions = [],
        array $headers = [],
    ): JsonResponse {
        $title ??= (string) config("api.codes.$code.title", Response::$statusTexts[$status] ?? 'Error');

        $details = new ProblemDetails(
            type: $code === 'internal_server_error' ? 'about:blank' : $this->baseUrl.'/'.$code,
            title: $title,
            status: $status,
            detail: $detail ?? $title,
            instance: $request?->fullUrl() ?? url()->current(),
            code: $code,
            traceId: $this->traceId(),
            errors: $errors,
            extensions: $extensions,
        );

        if ($status < 500) {
            $this->logProblem($details, $request);
        }

        return $details->toResponse()->withHeaders($headers);
    }

    /**
     * Record client errors (4xx) with their full problem payload so the log
     * shows the same detail the client received. Server errors (5xx) are left
     * to the ExceptionReporter, which logs the exception stack trace.
     *
     * @param  array<string, mixed>  $errors
     */
    private function logProblem(ProblemDetails $details, ?Request $request): void
    {
        $request ??= request();

        Log::warning('API problem response', [
            'code' => $details->code,
            'status' => $details->status,
            'title' => $details->title,
            'detail' => $details->detail,
            'errors' => $details->errors,
            'trace_id' => $details->traceId,
            'method' => $request?->method(),
            'path' => $request?->path(),
            'instance' => $details->instance,
        ]);
    }

    private function traceId(): ?string
    {
        $traceId = Context::get('trace_id');

        return is_string($traceId) ? $traceId : null;
    }

    private function slugForStatus(int $status): string
    {
        return (string) match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            415 => 'unsupported_media_type',
            422 => 'unprocessable_entity',
            429 => 'rate_limited',
            default => 'http_error',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function debugExtensions(Throwable $e): array
    {
        return [
            'debug' => [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())
                    ->map(fn (array $trace) => Arr::except($trace, ['args']))
                    ->all(),
            ],
        ];
    }
}
