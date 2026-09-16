<?php

use App\Shared\Exceptions\ApiException;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\ProblemDetails;
use App\Shared\Http\ProblemDetailsFactory;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

arch('api exception subclasses extend the base ApiException')
    ->expect('App\Shared\Exceptions')
    ->toExtend(ApiException::class)
    ->ignoring(ApiException::class);

arch('module domain exceptions extend the base ApiException')
    ->expect([
        'App\Modules\Customer\Domain\Exceptions',
        'App\Modules\Geography\Domain\Exceptions',
        'App\Modules\BankDirectory\Domain\Exceptions',
        'App\Modules\Service\Domain\Exceptions',
        'App\Modules\Storage\Domain\Exceptions',
        'App\Modules\IdentityAccess\Domain\Exceptions',
        'App\Modules\Merchant\Domain\Exceptions',
        'App\Modules\Payout\Domain\Exceptions',
    ])
    ->toExtend(ApiException::class);

arch('result and problem value objects are final and readonly')
    ->expect([Result::class, ResultError::class, ProblemDetails::class])
    ->toBeFinal()
    ->toBeReadonly();

arch('the API response helper is only used by module controllers')
    ->expect(ApiResponse::class)
    ->toOnlyBeUsedIn([
        'App\Modules\BankDirectory\Http\Controllers',
        'App\Modules\Customer\Http\Controllers',
        'App\Modules\Geography\Http\Controllers',
        'App\Modules\IdentityAccess\Http\Controllers',
        'App\Modules\Merchant\Http\Account',
        'App\Modules\Merchant\Http\Approval',
        'App\Modules\Merchant\Http\Catalog',
        'App\Modules\Merchant\Http\Registration',
        'App\Modules\Payout\Http\Controllers',
        'App\Modules\Service\Http\Controllers',
    ]);

arch('problem details are only produced by the response helper and factory')
    ->expect(ProblemDetails::class)
    ->toOnlyBeUsedIn([
        ProblemDetailsFactory::class,
        ApiResponse::class,
    ]);
