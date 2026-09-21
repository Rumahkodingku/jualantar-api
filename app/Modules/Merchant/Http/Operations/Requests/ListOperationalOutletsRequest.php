<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use App\Modules\Merchant\Domain\Enums\OutletStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOperationalOutletsRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(OutletStatus::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
