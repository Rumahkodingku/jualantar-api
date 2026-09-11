<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Customer\Application\Actions\Login;
use App\Modules\Customer\Application\Actions\RegisterCustomer;
use App\Modules\Customer\Application\Actions\VerifyEmail;
use App\Modules\Customer\Http\Requests\LoginRequest;
use App\Modules\Customer\Http\Requests\RegisterCustomerRequest;
use App\Modules\Customer\Http\Requests\ResendVerificationRequest;
use App\Modules\Customer\Http\Resources\RegisteredCustomerResource;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Http\Resources\UserResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AuthenticationController extends Controller
{
    public function __construct(
        private readonly RegisterCustomer $registerCustomer,
        private readonly VerifyEmail $verifyEmail,
        private readonly Login $login,
    ) {}

    #[OpenApiResponse(201, 'Customer registered', type: 'array{data: \App\Modules\Customer\Http\Resources\RegisteredCustomerResource}')]
    #[IgnoreResponse(200)]
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->registerCustomer)($request->validated()),
            fn (User $user) => ApiResponse::created(new RegisteredCustomerResource($user)),
        );
    }

    #[OpenApiResponse(200, 'Authenticated', type: 'array{data: array{token: string, token_type: string, user: \App\Modules\IdentityAccess\Http\Resources\UserResource}}')]
    public function login(LoginRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->login)($request->validated()),
            fn (array $result) => ApiResponse::success([
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => new UserResource($result['user']),
            ]),
        );
    }

    #[OpenApiResponse(204, 'Email verified')]
    #[IgnoreResponse(200)]
    public function verifyEmail(string $id, string $hash): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->verifyEmail)($id, $hash),
            fn () => ApiResponse::noContent(),
        );
    }

    #[OpenApiResponse(202, 'Verification email sent')]
    #[IgnoreResponse(200)]
    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            Log::info('verification_resend', ['user_id' => $user->id]);
        }

        return ApiResponse::success(null, 202);
    }
}
