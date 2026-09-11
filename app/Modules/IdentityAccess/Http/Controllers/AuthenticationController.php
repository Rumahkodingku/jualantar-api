<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Modules\IdentityAccess\Application\Actions\Login;
use App\Modules\IdentityAccess\Application\Actions\Logout;
use App\Modules\IdentityAccess\Application\Actions\ResendVerificationEmail;
use App\Modules\IdentityAccess\Application\Actions\VerifyEmail;
use App\Modules\IdentityAccess\Http\Requests\LoginRequest;
use App\Modules\IdentityAccess\Http\Requests\ResendVerificationRequest;
use App\Modules\IdentityAccess\Http\Resources\UserResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthenticationController extends Controller
{
    public function __construct(
        private readonly Login $login,
        private readonly Logout $logout,
        private readonly VerifyEmail $verifyEmail,
        private readonly ResendVerificationEmail $resendVerificationEmail,
    ) {}

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

    #[OpenApiResponse(204, 'Logged out')]
    #[IgnoreResponse(200)]
    public function logout(Request $request): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->logout)($request->user()),
            fn () => ApiResponse::noContent(),
        );
    }

    #[OpenApiResponse(200, 'Current user', type: 'array{data: \App\Modules\IdentityAccess\Http\Resources\UserResource}')]
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()->load('roles')));
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
        return ApiResponse::fromResult(
            ($this->resendVerificationEmail)($request->validated('email')),
            fn () => ApiResponse::success(null, 202),
        );
    }
}
