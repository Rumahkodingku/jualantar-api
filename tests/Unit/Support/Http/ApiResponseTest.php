<?php

use App\Shared\Http\ApiResponse;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;
use Illuminate\Pagination\LengthAwarePaginator;

it('wraps success data in the data key', function () {
    $response = ApiResponse::success(['id' => 1]);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['data' => ['id' => 1]]);
});

it('creates a 201 response with a location header', function () {
    $response = ApiResponse::created(['id' => 1], 'https://example.test/api/v1/orders/1');

    expect($response->getStatusCode())->toBe(201)
        ->and($response->headers->get('Location'))->toBe('https://example.test/api/v1/orders/1')
        ->and($response->getData(true))->toBe(['data' => ['id' => 1]]);
});

it('creates a 201 response without a location header', function () {
    $response = ApiResponse::created(['id' => 1]);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->headers->has('Location'))->toBeFalse();
});

it('returns a no content response', function () {
    $response = ApiResponse::noContent();

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getContent())->toBe('');
});

it('builds the paginated envelope with meta', function () {
    $paginator = new LengthAwarePaginator(
        items: [['id' => 1]],
        total: 42,
        perPage: 15,
        currentPage: 2,
    );

    $response = ApiResponse::paginated($paginator);

    expect($response->getData(true))->toBe([
        'data' => [['id' => 1]],
        'meta' => [
            'current_page' => 2,
            'per_page' => 15,
            'total' => 42,
            'last_page' => 3,
        ],
    ]);
});

it('turns an ok result into a success response', function () {
    $response = ApiResponse::fromResult(
        Result::ok(['id' => 1]),
        fn (array $value) => ApiResponse::success($value),
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['data' => ['id' => 1]]);
});

it('turns an err result into a problem response', function () {
    $response = ApiResponse::fromResult(
        Result::err(new ResultError(code: 'stock.insufficient', message: 'Not enough stock.')),
        fn (mixed $value) => ApiResponse::success($value),
    );

    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->headers->get('Content-Type'))->toStartWith('application/problem+json')
        ->and($payload['code'])->toBe('stock.insufficient')
        ->and($payload['detail'])->toBe('Not enough stock.');
});
