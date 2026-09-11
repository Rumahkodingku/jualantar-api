<?php

namespace App\Modules\Geography\Http\Controllers;

use App\Modules\Geography\Application\Actions\SetRegionActiveStatus;
use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Http\Requests\UpdateRegionStatusRequest;
use App\Modules\Geography\Http\Resources\ProvinceResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProvinceController extends Controller
{
    public function __construct(
        private readonly SetRegionActiveStatus $setRegionActiveStatus,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
        ]);

        $query = Province::query()->orderBy('name');

        if (filled($search = $validated['search'] ?? null)) {
            $query->where(function ($query) use ($search): void {
                $query->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        if (filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $query->active();
        } else {
            $query->where('is_active', false);
        }

        return ApiResponse::success(ProvinceResource::collection($query->get()));
    }

    public function show(Province $province): JsonResponse
    {
        return ApiResponse::success(new ProvinceResource($province));
    }

    #[OpenApiResponse(200, 'Province status updated', type: 'array{data: \App\Modules\Geography\Http\Resources\ProvinceResource}')]
    public function update(UpdateRegionStatusRequest $request, Province $province): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->setRegionActiveStatus)($province, $request->boolean('is_active')),
            fn (Province $province) => ApiResponse::success(new ProvinceResource($province)),
        );
    }

    #[OpenApiResponse(204, 'Province deactivated')]
    #[IgnoreResponse(200)]
    public function destroy(Province $province): Response
    {
        ($this->setRegionActiveStatus)($province, false);

        return ApiResponse::noContent();
    }
}
