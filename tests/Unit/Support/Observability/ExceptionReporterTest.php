<?php

use App\Shared\Exceptions\NotFoundException;
use App\Shared\Observability\ExceptionReporter;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    Context::flush();
});

it('does not log expected api exceptions', function () {
    Log::spy();

    $result = app(ExceptionReporter::class)(new NotFoundException);

    expect($result)->toBeNull();
    Log::shouldNotHaveReceived('error');
});

it('does not log expected http exceptions below 500', function () {
    Log::spy();

    $result = app(ExceptionReporter::class)(new NotFoundHttpException);

    expect($result)->toBeNull();
    Log::shouldNotHaveReceived('error');
});

it('logs unexpected exceptions with request context and suppresses default logging', function () {
    Log::spy();
    Context::add('trace_id', 'abc-123');

    $result = app(ExceptionReporter::class)(new RuntimeException('boom'));

    expect($result)->toBeFalse();

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'boom'
                && $context['exception'] instanceof RuntimeException
                && $context['status'] === 500
                && $context['trace_id'] === 'abc-123';
        });
});
