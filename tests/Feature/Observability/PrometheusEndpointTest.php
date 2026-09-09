<?php

use Illuminate\Support\Facades\Route;
use Prometheus\CollectorRegistry;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/log', fn () => 'ok')->name('api.v1.log');
        Route::get('/broken', fn () => throw new RuntimeException('boom'));
    });

    app(CollectorRegistry::class)->wipeStorage();
    config(['app.debug' => false]);
});

it('exposes the metrics endpoint in the Prometheus text format', function () {
    $this->get('/metrics')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
});

it('contains request metrics after requests are handled', function () {
    $this->getJson('/api/v1/log')->assertOk();

    $this->get('/metrics')
        ->assertOk()
        ->assertSee('http_requests_total', false)
        ->assertSee('method="GET"', false)
        ->assertSee('route="api.v1.log"', false)
        ->assertSee('status="200"', false)
        ->assertSee('http_request_duration_seconds', false);
});

it('contains the error metric after a server error', function () {
    $this->getJson('/api/v1/broken')->assertStatus(500);

    $this->get('/metrics')
        ->assertOk()
        ->assertSee('http_errors_total', false)
        ->assertSee('status="500"', false);
});

it('does not expose sensitive data from requests', function () {
    $this->getJson('/api/v1/broken')->assertStatus(500);

    $content = $this->get('/metrics')->getContent();

    expect($content)->not->toContain('RuntimeException')
        ->and($content)->not->toContain('boom')
        ->and($content)->not->toContain('Authorization')
        ->and($content)->not->toContain('password')
        ->and($content)->not->toContain('trace_id');
});
