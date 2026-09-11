<?php

namespace App\Modules\Geography\Http\Controllers;

use App\Modules\Geography\Application\Actions\SetRegionActiveStatus;
use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Http\Requests\UpdateRegionStatusRequest;
use App\Modules\Geography\Http\Resources\DistrictResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DistrictController extends Controller
{
    public function __construct(
        private readonly SetRegionActiveStatus $setRegionActiveStatus,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'regency_id' => ['required', 'integer', 'exists:regencies,id'],
            'search' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = District::query()->where('regency_id', $validated['regency_id'])->orderBy('name');

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

        return ApiResponse::paginated($paginator, DistrictResource::collection($paginator->items()));
    }

    public function show(District $district): JsonResponse
    {
        return ApiResponse::success(new DistrictResource($district));
    }

    #[OpenApiResponse(200, 'District status updated', type: 'array{data: \App\Modules\Geography\Http\Resources\DistrictResource}')]
    public function update(UpdateRegionStatusRequest $request, District $district): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->setRegionActiveStatus)($district, $request->boolean('is_active')),
            fn (District $district) => ApiResponse::success(new DistrictResource($district)),
        );
    }

    #[OpenApiResponse(204, 'District deactivated')]
    #[IgnoreResponse(200)]
    public function destroy(District $district): Response
    {
        ($this->setRegionActiveStatus)($district, false);

        return ApiResponse::noContent();
    }
}
