<?php

use App\Support\Http\ProblemDetailsFactory;
use App\Support\Result\ResultError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

it('defers to Laravel for non-API requests', function () {
    $factory = app(ProblemDetailsFactory::class);
    $request = Request::create('/web/route');

    expect($factory->render(new RuntimeException('boom'), $request))->toBeNull();
});

it('renders an unsupported media type exception as a 415 problem', function () {
    $factory = app(ProblemDetailsFactory::class);
    $request = Request::create('/api/v1/test', 'POST');

    $response = $factory->render(new UnsupportedMediaTypeHttpException('Unsupported media type.'), $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(415)
        ->and($response->getData(true)['code'])->toBe('unsupported_media_type');
});

it('renders an access denied exception as a 403 forbidden problem', function () {
    $factory = app(ProblemDetailsFactory::class);
    $request = Request::create('/api/v1/test');

    $response = $factory->render(new AccessDeniedHttpException('This action is unauthorized.'), $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['code'])->toBe('forbidden');
});

it('renders an authentication exception as a 401 problem', function () {
    $factory = app(ProblemDetailsFactory::class);
    $request = Request::create('/api/v1/test');

    $response = $factory->render(new AuthenticationException('Unauthenticated.'), $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(401)
        ->and($response->getData(true)['code'])->toBe('unauthenticated');
});

it('never leaks database internals in a 500 problem', function () {
    config(['app.debug' => false]);

    $factory = app(ProblemDetailsFactory::class);
    $request = Request::create('/api/v1/test');
    $exception = new QueryException('pgsql', 'select * from secret_users', [], new RuntimeException('boom'));

    $response = $factory->render($exception, $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['code'])->toBe('internal_server_error')
        ->and($response->getContent())->not->toContain('secret_users')
        ->and($response->getContent())->not->toContain('select');
});

it('logs a warning with the full payload for result-based client errors', function () {
    Log::spy();

    $factory = app(ProblemDetailsFactory::class);
    $request = Request::create('/api/v1/banks', 'POST');
    $error = new ResultError(
        code: 'conflict',
        message: 'A bank with the same code already exists.',
        status: 409,
        title: 'Conflict',
        fields: ['code' => ['The code has already been taken.']],
    );

    $response = $factory->problemFromResultError($error, $request);

    expect($response->getStatusCode())->toBe(409);

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'API problem response'
                && $context['code'] === 'conflict'
                && $context['status'] === 409
                && $context['errors']['code'][0] === 'The code has already been taken.'
                && $context['method'] === 'POST'
                && $context['path'] === 'api/v1/banks';
        });
});

it('does not log a warning for 500 problems', function () {
    Log::spy();

    config(['app.debug' => false]);

    $factory = app(ProblemDetailsFactory::class);
    $request = Request::create('/api/v1/test');
    $exception = new QueryException('pgsql', 'select * from secret_users', [], new RuntimeException('boom'));

    $response = $factory->render($exception, $request);

    expect($response->getStatusCode())->toBe(500);

    Log::shouldNotHaveReceived('warning');
});
