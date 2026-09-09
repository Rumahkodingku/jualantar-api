<?php

use App\Support\Http\ProblemDetailsFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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
