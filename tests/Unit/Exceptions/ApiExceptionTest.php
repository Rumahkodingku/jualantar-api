<?php

use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\ConflictException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Exceptions\UnprocessableEntityException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

it('maps a not found exception to 404 with the not_found code', function () {
    $exception = new NotFoundException;

    expect($exception->getStatusCode())->toBe(404)
        ->and($exception->problemCode())->toBe('not_found');
});

it('maps a conflict exception to 409 with the conflict code', function () {
    $exception = new ConflictException;

    expect($exception->getStatusCode())->toBe(409)
        ->and($exception->problemCode())->toBe('conflict');
});

it('maps a bad request exception to 400 with the bad_request code', function () {
    $exception = new BadRequestException;

    expect($exception->getStatusCode())->toBe(400)
        ->and($exception->problemCode())->toBe('bad_request');
});

it('carries a custom code and field errors for unprocessable entities', function () {
    $exception = new UnprocessableEntityException(
        message: 'The order cannot be shipped yet.',
        code: 'order.not_shippable',
        errors: ['status' => ['The order cannot be shipped while pending.']],
    );

    expect($exception->getStatusCode())->toBe(422)
        ->and($exception->problemCode())->toBe('order.not_shippable')
        ->and($exception->errors())->toBe(['status' => ['The order cannot be shipped while pending.']]);
});

it('extends the Symfony HttpException contract', function () {
    $exception = new ConflictException;

    expect($exception)->toBeInstanceOf(HttpExceptionInterface::class);
});
