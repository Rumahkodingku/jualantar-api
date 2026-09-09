<?php

use App\Http\Middleware\RequestIdMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Context::flush();
});

it('generates a trace id and shares it with the context and response', function () {
    $middleware = app(RequestIdMiddleware::class);
    $request = Request::create('/api/v1/test');

    $response = $middleware->handle($request, fn (Request $request) => new Response);

    $traceId = $response->headers->get('X-Trace-Id');

    expect($traceId)->toBeString()->not->toBeEmpty()
        ->and(Context::get('trace_id'))->toBe($traceId);
});

it('reuses an incoming X-Trace-Id header', function () {
    $middleware = app(RequestIdMiddleware::class);
    $request = Request::create('/api/v1/test', 'GET', [], [], [], ['HTTP_X_TRACE_ID' => 'client-trace-123']);

    $response = $middleware->handle($request, fn (Request $request) => new Response);

    expect($response->headers->get('X-Trace-Id'))->toBe('client-trace-123')
        ->and(Context::get('trace_id'))->toBe('client-trace-123');
});
