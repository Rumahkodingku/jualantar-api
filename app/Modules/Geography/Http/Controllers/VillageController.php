<?php

namespace App\Modules\Geography\Http\Controllers;

use App\Modules\Geography\Application\Actions\SetRegionActiveStatus;
use App\Modules\Geography\Domain\Models\Village;
use App\Modules\Geography\Http\Requests\UpdateRegionStatusRequest;
use App\Modules\Geography\Http\Resources\VillageResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class VillageController extends Controller
{
    public function __construct(
        private readonly SetRegionActiveStatus $setRegionActiveStatus,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'search' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Village::query()->where('district_id', $validated['district_id'])->orderBy('name');

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

        $paginator = $query->paginate($validated['per_page'] ?? 50)->withQueryString();

        return ApiResponse::paginated($paginator, VillageResource::collection($paginator->items()));
    }

    public function show(Village $village): JsonResponse
    {
        return ApiResponse::success(new VillageResource($village));
    }

    #[OpenApiResponse(200, 'Village status updated', type: 'array{data: \App\Modules\Geography\Http\Resources\VillageResource}')]
    public function update(UpdateRegionStatusRequest $request, Village $village): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->setRegionActiveStatus)($village, $request->boolean('is_active')),
            fn (Village $village) => ApiResponse::success(new VillageResource($village)),
        );
    }

    #[OpenApiResponse(204, 'Village deactivated')]
    #[IgnoreResponse(200)]
    public function destroy(Village $village): Response
    {
        ($this->setRegionActiveStatus)($village, false);

        return ApiResponse::noContent();
    }
}
