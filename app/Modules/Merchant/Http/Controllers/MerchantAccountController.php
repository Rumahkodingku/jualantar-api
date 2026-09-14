<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use App\Modules\Merchant\Application\Actions\RegisterMerchantAccount;
use App\Modules\Merchant\Http\Requests\RegisterMerchantAccountRequest;
use App\Modules\Merchant\Http\Resources\RegisteredMerchantResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MerchantAccountController extends Controller
{
    public function __construct(
        private readonly RegisterMerchantAccount $registerMerchantAccount,
    ) {}

    public function register(RegisterMerchantAccountRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->registerMerchantAccount)($request->validated()),
            fn (UserData $user) => ApiResponse::created(new RegisteredMerchantResource($user)),
        );
    }
}
