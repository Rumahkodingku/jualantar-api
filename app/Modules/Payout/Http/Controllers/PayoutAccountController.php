<?php

namespace App\Modules\Payout\Http\Controllers;

use App\Modules\BankDirectory\Contracts\BankLookup;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use App\Modules\Payout\Http\Requests\IndexPayoutAccountRequest;
use App\Modules\Payout\Http\Resources\PayoutAccountResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PayoutAccountController extends Controller
{
    public function __construct(private readonly BankLookup $bankLookup) {}

    public function index(IndexPayoutAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = PayoutAccount::query();

        if (filled($search = $validated['search'] ?? null)) {
            $query->where(function ($query) use ($search): void {
                $query->where('account_number', 'ilike', "%{$search}%")
                    ->orWhere('account_name', 'ilike', "%{$search}%");
            });
        }

        if (isset($validated['owner_type'])) {
            $query->where('owner_type', $validated['owner_type']);
        }

        if (isset($validated['owner_id'])) {
            $query->where('owner_id', $validated['owner_id']);
        }

        if (isset($validated['bank_id'])) {
            $query->where('bank_id', $validated['bank_id']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (array_key_exists('is_primary', $validated)) {
            $query->where('is_primary', filter_var($validated['is_primary'], FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy($validated['sort'] ?? 'created_at', $validated['order'] ?? 'desc');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        $accounts = $paginator->items();
        $banks = $this->bankLookup->banks(array_map(
            fn (PayoutAccount $account): int => $account->bank_id,
            $accounts,
        ));

        $data = array_map(
            fn (PayoutAccount $account) => new PayoutAccountResource($account, $banks[$account->bank_id] ?? null),
            $accounts,
        );

        return ApiResponse::paginated($paginator, $data);
    }

    public function show(PayoutAccount $payoutAccount): JsonResponse
    {
        $bank = $this->bankLookup->banks([$payoutAccount->bank_id])[$payoutAccount->bank_id] ?? null;

        return ApiResponse::success(new PayoutAccountResource($payoutAccount, $bank));
    }
}
