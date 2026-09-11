<?php

namespace App\Modules\Geography\Http\Controllers;

use App\Modules\Geography\Application\Actions\SetRegionActiveStatus;
use App\Modules\Geography\Domain\Models\Regency;
use App\Modules\Geography\Http\Requests\UpdateRegionStatusRequest;
use App\Modules\Geography\Http\Resources\RegencyResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class RegencyController extends Controller
{
    public function __construct(
        private readonly SetRegionActiveStatus $setRegionActiveStatus,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'is_active' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
        ]);

        $query = Regency::query()->orderBy('name');

        if (filled($search = $validated['search'] ?? null)) {
            $query->where(function ($query) use ($search): void {
                $query->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        if (isset($validated['province_id'])) {
            $query->where('province_id', $validated['province_id']);
        }

        if (filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $query->active();
        } else {
            $query->where('is_active', false);
        }

        return ApiResponse::success(RegencyResource::collection($query->get()));
    }

    public function show(Regency $regency): JsonResponse
    {
        return ApiResponse::success(new RegencyResource($regency));
    }

    #[OpenApiResponse(200, 'Regency status updated', type: 'array{data: \App\Modules\Geography\Http\Resources\RegencyResource}')]
    public function update(UpdateRegionStatusRequest $request, Regency $regency): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->setRegionActiveStatus)($regency, $request->boolean('is_active')),
            fn (Regency $regency) => ApiResponse::success(new RegencyResource($regency)),
        );
    }

    #[OpenApiResponse(204, 'Regency deactivated')]
    #[IgnoreResponse(200)]
    public function destroy(Regency $regency): Response
    {
        ($this->setRegionActiveStatus)($regency, false);

        return ApiResponse::noContent();
    }
}
