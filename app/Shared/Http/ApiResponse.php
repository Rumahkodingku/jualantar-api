<?php

namespace App\Shared\Http;

use App\Shared\Result\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json(['data' => $data], $status, $headers);
    }

    public static function created(mixed $data = null, ?string $location = null): JsonResponse
    {
        $headers = $location !== null ? ['Location' => $location] : [];

        return self::success($data, 201, $headers);
    }

    public static function noContent(int $status = 204): Response
    {
        return response()->noContent($status);
    }

    public static function paginated(LengthAwarePaginator $paginator, mixed $data = null): JsonResponse
    {
        return response()->json([
            'data' => $data ?? $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public static function problem(ProblemDetails $problem): JsonResponse
    {
        return $problem->toResponse();
    }

    /**
     * Translate a business Result into a success or problem response.
     *
     * The $ok callback receives the unwrapped value and must return a response,
     * so callers can choose the status (e.g. ApiResponse::created()).
     */
    public static function fromResult(Result $result, callable $ok, ?Request $request = null): Response|JsonResponse
    {
        if ($result->isOk()) {
            return $ok($result->unwrap());
        }

        return app(ProblemDetailsFactory::class)->problemFromResultError($result->error(), $request);
    }
}
