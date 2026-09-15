<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::get('/ping', fn () => 'pong');
    });

    config([
        'cors.allowed_origins' => [
            'http://localhost:5173',
            'http://jualantar.test',
            'http://jualantar.test:*',
            'http://*.jualantar.test',
            'http://*.jualantar.test:*',
        ],
    ]);
});

it('allows a preflight request from a frontend subdomain origin', function () {
    $origin = 'http://admin.jualantar.test:5173';

    $this->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/ping')
        ->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Origin', $origin);
});

it('allows a preflight request from the apex landing origin', function () {
    $origin = 'http://jualantar.test:3000';

    $this->withHeaders([
        'Origin' => $origin,
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/ping')
        ->assertStatus(204)
        ->assertHeader('Access-Control-Allow-Origin', $origin);
});

it('echoes the origin on an actual request from an allowed frontend', function () {
    $origin = 'http://merchant.jualantar.test:5173';

    $this->withHeaders(['Origin' => $origin])
        ->getJson('/api/v1/ping')
        ->assertStatus(200)
        ->assertHeader('Access-Control-Allow-Origin', $origin);
});

it('does not allow an origin outside the allowlist', function () {
    $this->withHeaders([
        'Origin' => 'http://evil.example.com',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/ping')
        ->assertStatus(204)
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});
