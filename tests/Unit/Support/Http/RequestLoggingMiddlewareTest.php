<?php

use App\Http\Middleware\RequestLoggingMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Prometheus\CollectorRegistry;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Context::flush();
    app(CollectorRegistry::class)->wipeStorage();
    config(['api.observability.enabled' => true]);
});

it('logs a completed request with method, path, status, duration, and trace id', function () {
    Log::spy();
    Context::add('trace_id', 'abc-123');
    $registry = app(CollectorRegistry::class);

    $middleware = new RequestLoggingMiddleware($registry);
    $request = Request::create('/api/v1/user', 'GET');

    $response = $middleware->handle($request, fn (Request $request) => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'HTTP request completed'
                && $context['method'] === 'GET'
                && $context['path'] === 'api/v1/user'
                && $context['status'] === 200
                && $context['trace_id'] === 'abc-123'
                && is_int($context['duration_ms'])
                && $context['duration_ms'] >= 0;
        });

    expect(counterValue($registry, 'http_requests_total', ['GET', 'unrouted', '200']))->toBe(1);
});

it('records an error metric and still logs when the request throws', function () {
    Log::spy();
    Context::add('trace_id', 'abc-123');
    $registry = app(CollectorRegistry::class);

    $middleware = new RequestLoggingMiddleware($registry);
    $request = Request::create('/api/v1/broken', 'GET');

    expect(fn () => $middleware->handle($request, fn (Request $request) => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'HTTP request completed'
            && $context['status'] === 500
            && $context['trace_id'] === 'abc-123');

    expect(counterValue($registry, 'http_requests_total', ['GET', 'unrouted', '500']))->toBe(1)
        ->and(counterValue($registry, 'http_errors_total', ['GET', 'unrouted', '500']))->toBe(1);
});

it('skips logging and metrics for the health endpoint', function () {
    Log::spy();
    $registry = app(CollectorRegistry::class);

    $middleware = new RequestLoggingMiddleware($registry);
    $request = Request::create('/up', 'GET');

    $response = $middleware->handle($request, fn (Request $request) => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200);

    Log::shouldNotHaveReceived('info');

    expect(counterValue($registry, 'http_requests_total', ['GET', 'unrouted', '200']))->toBe(0);
});

it('does nothing when observability is disabled', function () {
    config(['api.observability.enabled' => false]);
    Log::spy();
    $registry = app(CollectorRegistry::class);

    $middleware = new RequestLoggingMiddleware($registry);
    $request = Request::create('/api/v1/user', 'GET');

    $middleware->handle($request, fn (Request $request) => new Response('ok', 200));

    Log::shouldNotHaveReceived('info');

    expect(counterValue($registry, 'http_requests_total', ['GET', 'unrouted', '200']))->toBe(0);
});

it('still responds when the metrics storage fails', function () {
    Log::spy();
    Context::add('trace_id', 'abc-123');

    $registry = Mockery::mock(CollectorRegistry::class);
    $registry->shouldReceive('getOrRegisterCounter')->andThrow(new RuntimeException('storage down'));

    $middleware = new RequestLoggingMiddleware($registry);
    $request = Request::create('/api/v1/user', 'GET');

    $response = $middleware->handle($request, fn (Request $request) => new Response('ok', 200));

    expect($response->getStatusCode())->toBe(200);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message) => $message === 'HTTP request completed');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'Failed to record request metrics'
            && $context['trace_id'] === 'abc-123'
            && $context['error'] === 'storage down');
});
