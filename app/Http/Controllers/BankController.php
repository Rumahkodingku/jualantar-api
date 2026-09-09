<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use App\Services\BankService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function __construct(private readonly BankService $bankService) {}

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

    public function store(StoreBankRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->bankService->store($request->validated()),
            fn(Bank $bank) => ApiResponse::created(
                new BankResource($bank),
                route('api.v1.banks.show', $bank),
            ),
        );
    }

    public function update(Bank $bank, UpdateBankRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->bankService->update($bank, $request->validated()),
            fn(Bank $bank) => ApiResponse::success(new BankResource($bank)),
        );
    }

    public function destroy(Bank $bank): Response
    {
        $this->bankService->deactivate($bank);

        return ApiResponse::noContent();
    }
}
