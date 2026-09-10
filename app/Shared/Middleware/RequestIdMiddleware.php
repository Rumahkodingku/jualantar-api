<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $this->resolveTraceId($request);

        Context::add('trace_id', $traceId);

        $response = $next($request);

        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }

    private function resolveTraceId(Request $request): string
    {
        $incoming = trim((string) $request->header('X-Trace-Id', ''));

        if ($incoming !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::uuid();
    }
}
