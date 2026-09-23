<?php

use App\Modules\Merchant\Http\Middleware\EnsureMerchantContext;
use App\Modules\Merchant\Http\Middleware\EnsureMerchantOwner;
use App\Shared\Http\ProblemDetailsFactory;
use App\Shared\Middleware\RequestIdMiddleware;
use App\Shared\Middleware\RequestLoggingMiddleware;
use App\Shared\Observability\ExceptionReporter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'merchant.context' => EnsureMerchantContext::class,
            'merchant.owner' => EnsureMerchantOwner::class,
        ]);

        $middleware->redirectGuestsTo(fn () => null);

        $middleware->api(append: [
            RequestIdMiddleware::class,
            RequestLoggingMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(fn (Throwable $e) => app(ExceptionReporter::class)($e));

        $exceptions->render(fn (Throwable $e, Request $request) => app(ProblemDetailsFactory::class)->render($e, $request),
        );
    })->create();
