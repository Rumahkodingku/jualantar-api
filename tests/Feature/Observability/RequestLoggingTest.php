<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/log', fn () => 'ok')->name('api.v1.log');
        Route::get('/forbidden', fn () => abort(403));
    });
});

it('logs a successful HTTP request with request context', function () {
    Log::spy();

    $this->getJson('/api/v1/log')->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'HTTP request completed'
                && $context['method'] === 'GET'
                && $context['path'] === 'api/v1/log'
                && $context['route'] === 'api.v1.log'
                && $context['status'] === 200
                && is_int($context['duration_ms'])
                && $context['duration_ms'] >= 0;
        });
});

it('logs an error response with its status', function () {
    Log::spy();

    $this->getJson('/api/v1/forbidden')->assertStatus(403);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context) => $message === 'HTTP request completed'
            && $context['status'] === 403
            && $context['route'] === 'api/v1/forbidden');
});

it('does not log the health check endpoint', function () {
    Log::spy();

    $this->get('/up')->assertOk();

    Log::shouldNotHaveReceived('info');
});
