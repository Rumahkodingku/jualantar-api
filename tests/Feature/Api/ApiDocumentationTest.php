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
        '/v1/user',
        '/v1/users/{user}/roles',
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
        ->and($spec['paths']['/v1/user']['get'])->not->toHaveKey('security');
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
