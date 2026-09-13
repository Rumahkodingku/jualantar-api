<?php

namespace App\Modules\Payout\Http\Requests;

use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Enums\PayoutStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPayoutAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'owner_type' => ['nullable', Rule::in(PayoutOwnerType::values())],
            'owner_id' => ['nullable', 'uuid'],
            'bank_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(PayoutStatus::values())],
            'is_primary' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'sort' => ['nullable', Rule::in(['created_at', 'updated_at', 'account_name'])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
