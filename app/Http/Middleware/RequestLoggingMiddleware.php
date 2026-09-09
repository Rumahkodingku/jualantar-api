<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Prometheus\CollectorRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RequestLoggingMiddleware
{
    public function __construct(private readonly CollectorRegistry $registry) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('api.observability.enabled') || $this->shouldSkip($request)) {
            return $next($request);
        }

        $this->shareRequestContext($request);

        $startedAt = hrtime(true);

        try {
            $response = $next($request);

            $this->record($request, $response->getStatusCode(), $startedAt);

            return $response;
        } catch (Throwable $e) {
            $this->record($request, $this->statusFor($e), $startedAt);

            throw $e;
        }
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is('up', 'health');
    }

    private function shareRequestContext(Request $request): void
    {
        Context::add('http_method', $request->method());
        Context::add('http_path', $request->path());

        if (! $request->hasHeader('Authorization')) {
            return;
        }

        $user = $request->user();

        if ($user !== null) {
            Context::add('user_id', (string) $user->getKey());
        }
    }

    private function record(Request $request, int $status, int $startedAt): void
    {
        $elapsedNs = hrtime(true) - $startedAt;
        $route = $this->routeLabel($request);

        Log::info('HTTP request completed', [
            'trace_id' => is_string(Context::get('trace_id')) ? (string) Context::get('trace_id') : null,
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $route,
            'status' => $status,
            'duration_ms' => (int) round($elapsedNs / 1_000_000),
            'ip' => $request->ip(),
        ]);

        try {
            $this->recordMetrics($request, $status, $elapsedNs, $route);
        } catch (Throwable $e) {
            Log::warning('Failed to record request metrics', [
                'trace_id' => is_string(Context::get('trace_id')) ? (string) Context::get('trace_id') : null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function recordMetrics(Request $request, int $status, int $elapsedNs, string $route): void
    {
        $labels = [
            'method' => $request->method(),
            'route' => $route,
            'status' => (string) $status,
        ];

        $this->registry->getOrRegisterCounter(
            '',
            'http_requests_total',
            'Total HTTP requests handled.',
            ['method', 'route', 'status'],
        )->incBy(1, array_values($labels));

        if ($status >= 500) {
            $this->registry->getOrRegisterCounter(
                '',
                'http_errors_total',
                'HTTP requests that resulted in a server error (status >= 500).',
                ['method', 'route', 'status'],
            )->incBy(1, array_values($labels));
        }

        $this->registry->getOrRegisterHistogram(
            '',
            'http_request_duration_seconds',
            'HTTP request duration in seconds.',
            ['method', 'route'],
        )->observe($elapsedNs / 1_000_000_000, [$request->method(), $route]);
    }

    private function routeLabel(Request $request): string
    {
        $route = $request->route();

        if ($route === null) {
            return 'unrouted';
        }

        $name = $route->getName();

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return $route->uri();
    }

    private function statusFor(Throwable $e): int
    {
        return $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
    }
}
