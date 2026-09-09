<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/protected', fn () => 'ok')->middleware('auth:sanctum');
        Route::get('/forbidden', fn () => abort(403));
        Route::get('/only-get', fn () => 'ok');
        Route::post('/validate', fn () => request()->validate([
            'email' => ['required', 'email'],
        ]));
        Route::get('/limited', fn () => 'ok')->middleware('throttle:1,1');
        Route::get('/broken', fn () => throw new RuntimeException('boom'));
    });

    Route::get('/web-broken', fn () => throw new RuntimeException('web-boom'));
});

it('returns a 401 unauthenticated problem when no token is provided', function () {
    $this->getJson('/api/v1/protected')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', config('api.problem.base_url').'/unauthenticated')
        ->assertJsonPath('title', 'Unauthenticated')
        ->assertJsonPath('status', 401)
        ->assertJsonPath('detail', 'Unauthenticated.')
        ->assertJsonPath('instance', url('api/v1/protected'))
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns a 403 forbidden problem when access is denied', function () {
    $this->getJson('/api/v1/forbidden')
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', config('api.problem.base_url').'/forbidden')
        ->assertJsonPath('status', 403)
        ->assertJsonPath('code', 'forbidden');
});

it('returns a 404 not found problem for an unknown route', function () {
    $this->getJson('/api/v1/nonexistent')
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', config('api.problem.base_url').'/not_found')
        ->assertJsonPath('detail', 'The requested resource was not found.')
        ->assertJsonPath('code', 'not_found');
});

it('returns a 405 method not allowed problem for an unsupported verb', function () {
    $this->postJson('/api/v1/only-get')
        ->assertStatus(405)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'method_not_allowed')
        ->assertJsonPath('status', 405);
});

it('returns a 422 validation problem with field errors', function () {
    $this->postJson('/api/v1/validate', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('status', 422)
        ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.');
});

it('returns a 429 rate limited problem on the second request', function () {
    $this->getJson('/api/v1/limited')->assertStatus(200);

    $this->getJson('/api/v1/limited')
        ->assertStatus(429)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'rate_limited')
        ->assertJsonPath('status', 429)
        ->assertHeader('Retry-After', '60');
});

it('returns a generic 500 problem without leaking details', function () {
    config(['app.debug' => false]);

    $this->getJson('/api/v1/broken')
        ->assertStatus(500)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'about:blank')
        ->assertJsonPath('code', 'internal_server_error')
        ->assertJsonPath('detail', 'An unexpected error occurred.')
        ->assertJsonMissing(['exception', 'file', 'line', 'trace'])
        ->assertDontSee('RuntimeException')
        ->assertDontSee('boom');
});

it('includes debug details in a 500 problem when debug is enabled', function () {
    config(['app.debug' => true]);

    $this->getJson('/api/v1/broken')
        ->assertStatus(500)
        ->assertJsonPath('code', 'internal_server_error')
        ->assertJsonPath('debug.exception', RuntimeException::class);
});

it('shares the trace id between the header, the body, and the log context', function () {
    $response = $this->getJson('/api/v1/forbidden');

    $traceId = $response->headers->get('X-Trace-Id');

    expect($traceId)->toBeString()->not->toBeEmpty()
        ->and($response->json('trace_id'))->toBe($traceId);
});

it('does not render a problem response for non-API requests', function () {
    $response = $this->get('/web-broken');

    expect($response->headers->get('Content-Type'))->not->toContain('problem+json');
});
