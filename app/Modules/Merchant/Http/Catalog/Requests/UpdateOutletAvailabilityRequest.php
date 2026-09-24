<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOutletAvailabilityRequest extends FormRequest
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
            'status' => ['required', Rule::in(ProductAvailabilityStatus::values())],
            'reason' => [
                'nullable',
                'string',
                'max:255',
                'prohibited_if:status,'.ProductAvailabilityStatus::Available->value,
            ],
        ];
    }
}
