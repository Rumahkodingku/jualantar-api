<?php

use App\Support\Result\ResultError;

it('exposes its code, message, and default status', function () {
    $error = new ResultError(code: 'stock.insufficient', message: 'Not enough stock.');

    expect($error->code)->toBe('stock.insufficient')
        ->and($error->message)->toBe('Not enough stock.')
        ->and($error->status)->toBe(422)
        ->and($error->title)->toBe('Unprocessable Entity')
        ->and($error->fields)->toBe([])
        ->and($error->context)->toBe([]);
});

it('rejects a 5xx status', function () {
    expect(fn () => new ResultError(code: 'boom', message: 'Boom.', status: 500))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a 3xx status', function () {
    expect(fn () => new ResultError(code: 'redirect', message: 'Redirect.', status: 302))
        ->toThrow(InvalidArgumentException::class);
});

it('carries validation-style fields', function () {
    $error = new ResultError(
        code: 'validation_error',
        message: 'The given data was invalid.',
        fields: ['email' => ['The email field is required.']],
    );

    expect($error->fields)->toBe(['email' => ['The email field is required.']]);
});
