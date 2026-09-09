<?php

use App\Support\Result\Result;
use App\Support\Result\ResultError;

it('creates an ok result with a value', function () {
    $result = Result::ok(['id' => 1]);

    expect($result->isOk())->toBeTrue()
        ->and($result->isErr())->toBeFalse()
        ->and($result->unwrap())->toBe(['id' => 1]);
});

it('creates an err result without a value', function () {
    $error = new ResultError(code: 'stock.insufficient', message: 'Not enough stock.');
    $result = Result::err($error);

    expect($result->isOk())->toBeFalse()
        ->and($result->isErr())->toBeTrue()
        ->and($result->error())->toBe($error);
});

it('throws when unwrapping an error result', function () {
    $result = Result::err(new ResultError(code: 'conflict', message: 'Conflict.'));

    expect(fn () => $result->unwrap())->toThrow(LogicException::class);
});

it('throws when retrieving the error of an ok result', function () {
    $result = Result::ok(1);

    expect(fn () => $result->error())->toThrow(LogicException::class);
});

it('returns the default value for an error result via getOr', function () {
    $result = Result::err(new ResultError(code: 'conflict', message: 'Conflict.'));

    expect($result->getOr(42))->toBe(42);
});

it('maps the ok value and preserves the error', function () {
    $mapped = Result::ok(2)->map(fn (int $value) => $value * 3);

    expect($mapped->unwrap())->toBe(6);

    $error = new ResultError(code: 'conflict', message: 'Conflict.');
    $mappedError = Result::err($error)->map(fn (int $value) => $value * 3);

    expect($mappedError->error())->toBe($error);
});

it('maps the error and preserves the value', function () {
    $error = new ResultError(code: 'conflict', message: 'Conflict.');
    $mapped = Result::err($error)->mapError(
        fn (ResultError $error) => new ResultError(code: 'forbidden', message: 'Forbidden.'),
    );

    expect($mapped->error()->code)->toBe('forbidden');

    $mappedOk = Result::ok(1)->mapError(fn (ResultError $error) => $error);

    expect($mappedOk->unwrap())->toBe(1);
});

it('matches the ok branch with the value', function () {
    $result = Result::ok('done');

    $output = $result->match(
        ok: fn (string $value) => "ok:$value",
        err: fn (ResultError $error) => 'err',
    );

    expect($output)->toBe('ok:done');
});

it('matches the err branch with the error', function () {
    $result = Result::err(new ResultError(code: 'conflict', message: 'Conflict.'));

    $output = $result->match(
        ok: fn (mixed $value) => 'ok',
        err: fn (ResultError $error) => "err:{$error->code}",
    );

    expect($output)->toBe('err:conflict');
});
