<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::post('/log-validate', fn () => request()->validate([
            'code' => ['required'],
        ]));
        Route::get('/log-forbidden', fn () => abort(403));
    });
});

it('logs a validation problem with the field errors correlated to the trace id', function () {
    Log::spy();

    $response = $this->postJson('/api/v1/log-validate', []);

    $traceId = $response->json('trace_id');

    $response->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context) use ($traceId): bool {
            return $message === 'API problem response'
                && $context['code'] === 'validation_error'
                && $context['status'] === 422
                && $context['errors']['code'][0] === 'The code field is required.'
                && $context['trace_id'] === $traceId
                && $context['method'] === 'POST'
                && $context['path'] === 'api/v1/log-validate';
        });
});

it('logs a generic 4xx problem response', function () {
    Log::spy();

    $this->getJson('/api/v1/log-forbidden')->assertStatus(403);

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'API problem response'
                && $context['code'] === 'forbidden'
                && $context['status'] === 403;
        });
});
