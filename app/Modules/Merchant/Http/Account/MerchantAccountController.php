<?php

namespace App\Modules\Merchant\Http\Account;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use App\Modules\Merchant\Application\Account\RegisterAccount;
use App\Modules\Merchant\Http\Account\Requests\RegisterAccountRequest;
use App\Modules\Merchant\Http\Resources\RegisteredMerchantResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MerchantAccountController extends Controller
{
    public function __construct(
        private readonly RegisterAccount $registerMerchantAccount,
    ) {}

    public function register(RegisterAccountRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->registerMerchantAccount)($request->validated()),
            fn (UserData $user) => ApiResponse::created(new RegisteredMerchantResource($user)),
        );
    }
}
