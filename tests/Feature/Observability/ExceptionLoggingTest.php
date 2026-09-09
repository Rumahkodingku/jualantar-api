<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/broken', fn () => throw new RuntimeException('boom'));
    });

    config(['app.debug' => false]);
});

it('logs an unexpected exception correlated with the trace id', function () {
    Log::spy();

    $response = $this->getJson('/api/v1/broken');

    $traceId = $response->headers->get('X-Trace-Id');

    $response->assertStatus(500)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'internal_server_error')
        ->assertJsonMissing(['exception', 'file', 'line', 'trace'])
        ->assertDontSee('RuntimeException')
        ->assertDontSee('boom');

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context) use ($traceId): bool {
            return $message === 'boom'
                && $context['exception'] instanceof RuntimeException
                && $context['status'] === 500
                && $context['method'] === 'GET'
                && $context['path'] === 'api/v1/broken'
                && $context['trace_id'] === $traceId;
        });
});
