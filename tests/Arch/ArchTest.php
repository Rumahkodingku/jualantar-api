<?php

use App\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use App\Support\Http\ProblemDetails;
use App\Support\Http\ProblemDetailsFactory;
use App\Support\Result\Result;
use App\Support\Result\ResultError;

arch('api exception subclasses extend the base ApiException')
    ->expect('App\Exceptions')
    ->toExtend(ApiException::class)
    ->ignoring(ApiException::class);

arch('result and problem value objects are final and readonly')
    ->expect([Result::class, ResultError::class, ProblemDetails::class])
    ->toBeFinal()
    ->toBeReadonly();

arch('the API response helper is only used by controllers')
    ->expect(ApiResponse::class)
    ->toOnlyBeUsedIn('App\Http\Controllers');

arch('problem details are only produced by the response helper and factory')
    ->expect(ProblemDetails::class)
    ->toOnlyBeUsedIn([
        ProblemDetailsFactory::class,
        ApiResponse::class,
    ]);

arch('services are only used by controllers')
    ->expect('App\Services')
    ->toOnlyBeUsedIn('App\Http\Controllers');
