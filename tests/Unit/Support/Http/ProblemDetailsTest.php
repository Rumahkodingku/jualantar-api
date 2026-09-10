<?php

use App\Shared\Http\ProblemDetails;

it('serializes the core RFC 9457 fields', function () {
    $problem = new ProblemDetails(
        type: 'https://example.test/problems/not_found',
        title: 'Not Found',
        status: 404,
        detail: 'The requested resource was not found.',
        instance: 'https://example.test/api/v1/orders/1',
        code: 'not_found',
    );

    expect($problem->toArray())->toBe([
        'type' => 'https://example.test/problems/not_found',
        'title' => 'Not Found',
        'status' => 404,
        'detail' => 'The requested resource was not found.',
        'instance' => 'https://example.test/api/v1/orders/1',
        'code' => 'not_found',
    ]);
});

it('omits null trace_id and empty errors', function () {
    $problem = new ProblemDetails(
        type: 'about:blank',
        title: 'Internal Server Error',
        status: 500,
        detail: 'An unexpected error occurred.',
        instance: '/api/v1/orders',
        code: 'internal_server_error',
    );

    $payload = $problem->toArray();

    expect($payload)->not->toHaveKey('trace_id')
        ->and($payload)->not->toHaveKey('errors');
});

it('includes trace_id and errors when provided', function () {
    $problem = new ProblemDetails(
        type: 'https://example.test/problems/validation_error',
        title: 'Validation Failed',
        status: 422,
        detail: 'The given data was invalid.',
        instance: '/api/v1/orders',
        code: 'validation_error',
        traceId: 'abc-123',
        errors: ['email' => ['The email field is required.']],
    );

    $payload = $problem->toArray();

    expect($payload['trace_id'])->toBe('abc-123')
        ->and($payload['errors'])->toBe(['email' => ['The email field is required.']]);
});

it('appends extension fields after the core fields', function () {
    $problem = new ProblemDetails(
        type: 'about:blank',
        title: 'Internal Server Error',
        status: 500,
        detail: 'An unexpected error occurred.',
        instance: '/api/v1/orders',
        code: 'internal_server_error',
        extensions: ['debug' => ['exception' => 'RuntimeException']],
    );

    expect(array_keys($problem->toArray()))->toBe([
        'type', 'title', 'status', 'detail', 'instance', 'code', 'debug',
    ]);
});

it('builds a problem+json response', function () {
    $problem = new ProblemDetails(
        type: 'about:blank',
        title: 'Internal Server Error',
        status: 500,
        detail: 'An unexpected error occurred.',
        instance: '/api/v1/orders',
        code: 'internal_server_error',
    );

    $response = $problem->toResponse();

    expect($response->getStatusCode())->toBe(500)
        ->and($response->headers->get('Content-Type'))->toStartWith('application/problem+json');
});
