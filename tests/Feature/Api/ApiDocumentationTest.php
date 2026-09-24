<?php

it('serves the OpenAPI document', function () {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'JualAntar API')
        ->assertJsonPath('info.version', 'v1')
        ->assertJsonPath('openapi', '3.1.0');
});

it('renders the Scalar API reference UI', function () {
    $this->get('/docs/api')
        ->assertOk()
        ->assertSee('JualAntar API')
        ->assertSee('Scalar.createApiReference', false);
});

it('documents the module routes', function () {
    $paths = $this->getJson('/docs/api.json')->json('paths');

    expect($paths)->toHaveKeys([
        '/v1/banks',
        '/v1/banks/{bank}',
        '/v1/roles',
        '/v1/permissions',
        '/v1/auth/login',
        '/v1/auth/me',
        '/v1/customers/register',
        '/v1/users/{user}/roles',
        '/v1/admin/merchant-approvals',
        '/v1/admin/merchant-approvals/summary',
        '/v1/admin/merchant-approvals/{approval}',
        '/v1/admin/merchant-approvals/{approval}/claim',
        '/v1/admin/merchant-approvals/{approval}/approve',
    ]);
});

it('documents every error response as RFC 9457 problem details', function () {
    $spec = $this->getJson('/docs/api.json')->json();

    expect($spec['components']['schemas']['ProblemDetails']['required'])
        ->toBe(['type', 'title', 'status', 'detail', 'instance', 'code']);

    $forbidden = $spec['paths']['/v1/roles']['post']['responses']['403'];

    expect($forbidden['content']['application/problem+json']['schema']['$ref'])
        ->toBe('#/components/schemas/ProblemDetails')
        ->and($forbidden['content'])->not->toHaveKey('application/json');
});

it('secures protected routes with bearer auth and keeps public routes unsecured', function () {
    $spec = $this->getJson('/docs/api.json')->json();

    expect($spec['components']['securitySchemes']['http']['scheme'])->toBe('bearer')
        ->and($spec['security'])->toBe([['http' => []]])
        ->and($spec['paths']['/v1/banks']['get']['security'])->toBe([])
        ->and($spec['paths']['/v1/auth/me']['get'])->not->toHaveKey('security');
});

it('documents the merchant catalog endpoints', function () {
    $spec = $this->getJson('/docs/api.json')->json();

    expect($spec['paths'])->toHaveKeys([
        '/v1/merchant/catalog/categories',
        '/v1/merchant/catalog/categories/order',
        '/v1/merchant/catalog/categories/{category}',
        '/v1/merchant/catalog/products',
        '/v1/merchant/catalog/products/order',
        '/v1/merchant/catalog/products/{product}',
        '/v1/merchant/catalog/products/{product}/variants',
        '/v1/merchant/catalog/products/{product}/variants/order',
        '/v1/merchant/catalog/products/{product}/media',
        '/v1/merchant/catalog/products/{product}/media/upload-url',
        '/v1/merchant/catalog/products/{product}/media/order',
        '/v1/merchant/catalog/products/{product}/outlets',
        '/v1/merchant/catalog/products/{product}/outlets/{outlet}/availability',
        '/v1/merchant/catalog/products/{product}/modifier-groups',
        '/v1/merchant/catalog/products/{product}/modifier-groups/order',
        '/v1/merchant/catalog/products/{product}/modifier-groups/{group}',
        '/v1/merchant/catalog/products/{product}/modifier-groups/{group}/activate',
        '/v1/merchant/catalog/products/{product}/modifier-groups/{group}/modifiers',
        '/v1/merchant/catalog/products/{product}/modifier-groups/{group}/modifiers/order',
        '/v1/merchant/catalog/products/{product}/modifier-groups/{group}/modifiers/{modifier}',
        '/v1/merchant/catalog/outlets/{outlet}/products',
        '/v1/merchant/catalog/outlets/{outlet}/products/order',
    ]);

    expect($spec['paths']['/v1/merchant/catalog/products']['post']['responses'])
        ->toHaveKeys(['201', '401', '403', '404', '422'])
        ->and($spec['paths']['/v1/merchant/catalog/products/{product}/modifier-groups/{group}/activate']['post']['responses'])
        ->toHaveKeys(['200', '401', '403', '404', '409'])
        ->and($spec['paths']['/v1/merchant/catalog/products/{product}/modifier-groups/{group}/modifiers/{modifier}']['delete']['responses'])
        ->toHaveKeys(['204', '401', '403', '404', '409'])
        ->and($spec['paths']['/v1/merchant/catalog/categories/{category}']['delete']['responses'])
        ->toHaveKeys(['204', '401', '403', '404', '409'])
        ->and($spec['paths']['/v1/merchant/catalog/categories']['post']['responses']['201']['content']['application/json']['schema']['properties']['data']['$ref'])
        ->toBe('#/components/schemas/CatalogCategoryResource')
        ->and($spec['paths']['/v1/merchant/catalog/outlets/{outlet}/products']['get']['responses']['403']['content']['application/problem+json']['schema']['$ref'])
        ->toBe('#/components/schemas/ProblemDetails');
});

it('documents the success envelope for endpoints backed by the result pattern', function () {
    $spec = $this->getJson('/docs/api.json')->json();

    expect($spec['paths']['/v1/banks']['post']['responses'])->toHaveKeys(['201', '409', '422'])
        ->and($spec['paths']['/v1/banks']['post']['responses']['201']['content']['application/json']['schema']['properties']['data']['$ref'])
        ->toBe('#/components/schemas/BankResource')
        ->and($spec['paths']['/v1/roles']['post']['responses'])->toHaveKeys(['201', '401', '403', '409', '422'])
        ->and($spec['paths']['/v1/roles/{role}']['delete']['responses'])->toHaveKeys(['204', '401', '403', '404', '409'])
        ->and($spec['paths']['/v1/users/{user}/roles']['post']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'])
        ->toBe('#/components/schemas/RoleResource');
});
