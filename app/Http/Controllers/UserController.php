<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }
}
