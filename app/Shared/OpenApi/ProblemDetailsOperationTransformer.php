<?php

namespace App\Shared\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Documents every error response with the application's RFC 9457 problem
 * details payload instead of Scramble's default exception schemas.
 */
final class ProblemDetailsOperationTransformer implements OperationTransformer
{
    private const SCHEMA_NAME = 'ProblemDetails';

    /**
     * Middleware aliases that authorize the caller, so the operation may return
     * a 403 problem response. Covers the Spatie ability middleware plus the
     * merchant context gates used by the merchant APIs.
     *
     * @var list<string>
     */
    private const AUTHORIZATION_MIDDLEWARE = [
        'permission:',
        'role:',
        'can:',
        'merchant.context',
        'merchant.owner',
    ];

    /**
     * Route name prefixes whose actions resolve tenant-scoped resources with an
     * explicit lookup instead of route model binding, so they may return a 404
     * even though no path parameter is a bound model. Add a prefix here when a
     * new area follows the same "not found for another tenant" convention.
     *
     * @var list<string>
     */
    private const NOT_FOUND_ROUTE_PREFIXES = [
        'api.v1.merchant.catalog.',
    ];

    /**
     * Routes whose action may return a 409 conflict response.
     *
     * @var list<string>
     */
    private const CONFLICT_ROUTES = [
        'api.v1.banks.store',
        'api.v1.banks.update',
        'api.v1.roles.store',
        'api.v1.roles.update',
        'api.v1.roles.destroy',
        'api.v1.permissions.store',
        'api.v1.permissions.destroy',
        'api.v1.merchant.catalog.categories.destroy',
        'api.v1.merchant.catalog.products.activate',
        'api.v1.merchant.catalog.products.variants.deactivate',
        'api.v1.merchant.catalog.products.variants.destroy',
        'api.v1.merchant.catalog.products.media.upload_url',
        'api.v1.merchant.catalog.products.media.store',
        'api.v1.merchant.catalog.products.outlets.store',
    ];

    public function __construct(private readonly OpenApi $openApi) {}

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $reference = $this->problemReference();

        $middleware = collect($routeInfo->route->gatherMiddleware());

        $hasAuth = $middleware->contains(
            fn ($m) => is_string($m) && ($m === 'auth' || Str::startsWith($m, 'auth:')),
        );

        $hasAuthorization = $middleware->contains(
            fn ($m) => is_string($m) && Str::startsWith($m, self::AUTHORIZATION_MIDDLEWARE),
        );

        $statuses = [];

        $existing = $this->existingStatuses($operation);

        if ($hasAuth || isset($existing[401])) {
            $statuses[401] = 'Unauthenticated.';
        }

        if ($hasAuthorization || isset($existing[403])) {
            $statuses[403] = 'Forbidden.';
        }

        if ($this->hasModelParameter($operation) || $this->mayReturnNotFound($routeInfo) || isset($existing[404])) {
            $statuses[404] = 'The requested resource was not found.';
        }

        if (in_array($routeInfo->route->getName(), self::CONFLICT_ROUTES, true) || isset($existing[409])) {
            $statuses[409] = 'The request conflicts with the current state of the resource.';
        }

        if ($operation->requestBodyObject !== null || isset($existing[422])) {
            $statuses[422] = 'The given data failed validation.';
        }

        foreach ($statuses as $status => $description) {
            $operation->addResponse(
                Response::make($status)
                    ->setDescription($description)
                    ->setContent('application/problem+json', $reference),
            );
        }

        $this->stripNoContentBodies($operation);
    }

    private function mayReturnNotFound(RouteInfo $routeInfo): bool
    {
        $name = $routeInfo->route->getName();

        if ($name === null) {
            return false;
        }

        return Str::startsWith($name, self::NOT_FOUND_ROUTE_PREFIXES);
    }

    private function problemReference(): Reference
    {
        if (! $this->openApi->components->hasSchema(self::SCHEMA_NAME)) {
            $this->openApi->components->addSchema(self::SCHEMA_NAME, Schema::fromType($this->problemType()));
        }

        return $this->openApi->components->getSchemaReference(self::SCHEMA_NAME);
    }

    private function problemType(): ObjectType
    {
        $errors = (new ObjectType)->additionalProperties(
            (new ArrayType)->setItems(new StringType),
        );

        return (new ObjectType)
            ->addProperty('type', (new StringType)->format('uri'))
            ->addProperty('title', new StringType)
            ->addProperty('status', new IntegerType)
            ->addProperty('detail', new StringType)
            ->addProperty('instance', (new StringType)->format('uri'))
            ->addProperty('code', new StringType)
            ->addProperty('trace_id', (new StringType)->nullable(true))
            ->addProperty('errors', $errors)
            ->setRequired(['type', 'title', 'status', 'detail', 'instance', 'code']);
    }

    private function hasModelParameter(Operation $operation): bool
    {
        return collect($operation->parameters)->contains(
            fn ($parameter) => $parameter->in === 'path'
                && $parameter->schema?->type?->getAttribute('isModelId') === true,
        );
    }

    /**
     * @return array<int, true>
     */
    private function existingStatuses(Operation $operation): array
    {
        $statuses = [];

        foreach ($operation->responses ?? [] as $response) {
            $code = match (true) {
                $response instanceof Response => $response->code,
                $response instanceof Reference => $response->resolve()->code,
                default => null,
            };

            if (is_int($code) || (is_string($code) && ctype_digit($code))) {
                $statuses[(int) $code] = true;
            }
        }

        return $statuses;
    }

    private function stripNoContentBodies(Operation $operation): void
    {
        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Response && (int) $response->code === 204) {
                $response->content = [];
            }
        }
    }
}
