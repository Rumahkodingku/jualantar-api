<?php

namespace App\Modules\BankDirectory\Http\Controllers;

use App\Modules\BankDirectory\Application\Actions\DeactivateBankAccount;
use App\Modules\BankDirectory\Application\Actions\StoreBankAccount;
use App\Modules\BankDirectory\Application\Actions\UpdateBankAccount;
use App\Modules\BankDirectory\Domain\Models\Bank;
use App\Modules\BankDirectory\Http\Requests\StoreBankRequest;
use App\Modules\BankDirectory\Http\Requests\UpdateBankRequest;
use App\Modules\BankDirectory\Http\Resources\BankResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function __construct(
        private readonly StoreBankAccount $storeBankAccount,
        private readonly UpdateBankAccount $updateBankAccount,
        private readonly DeactivateBankAccount $deactivateBankAccount,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', Rule::in(Bank::CATEGORIES)],
            'is_active' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Bank::query()->orderBy('name');

        if (filled($search = $validated['search'] ?? null)) {
            $query->where(function ($query) use ($search): void {
                $query->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        if (isset($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        $query->where('is_active', filter_var($validated['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN));

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, BankResource::collection($paginator->items()));
    }

    public function show(Bank $bank): JsonResponse
    {
        return ApiResponse::success(new BankResource($bank));
    }

    #[OpenApiResponse(201, 'Bank created', type: 'array{data: \App\Modules\BankDirectory\Http\Resources\BankResource}')]
    #[IgnoreResponse(200)]
    public function store(StoreBankRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->storeBankAccount)($request->validated()),
            fn (Bank $bank) => ApiResponse::created(
                new BankResource($bank),
                route('api.v1.banks.show', $bank),
            ),
        );
    }

    #[OpenApiResponse(200, 'Bank updated', type: 'array{data: \App\Modules\BankDirectory\Http\Resources\BankResource}')]
    public function update(Bank $bank, UpdateBankRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->updateBankAccount)($bank, $request->validated()),
            fn (Bank $bank) => ApiResponse::success(new BankResource($bank)),
        );
    }

    public function destroy(Bank $bank): Response
    {
        ($this->deactivateBankAccount)($bank);

        return ApiResponse::noContent();
    }
}
