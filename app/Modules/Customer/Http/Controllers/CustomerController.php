<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Customer\Application\Actions\RegisterCustomer;
use App\Modules\Customer\Http\Requests\RegisterCustomerRequest;
use App\Modules\Customer\Http\Resources\RegisteredCustomerResource;
use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly RegisterCustomer $registerCustomer) {}

    #[OpenApiResponse(201, 'Customer registered', type: 'array{data: \App\Modules\Customer\Http\Resources\RegisteredCustomerResource}')]
    #[IgnoreResponse(200)]
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->registerCustomer)($request->validated()),
            fn (UserData $user) => ApiResponse::created(new RegisteredCustomerResource($user)),
        );
    }
}
