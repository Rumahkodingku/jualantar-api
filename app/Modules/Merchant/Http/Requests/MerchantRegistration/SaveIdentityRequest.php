<?php

namespace App\Modules\Merchant\Http\Requests\MerchantRegistration;

use App\Modules\Merchant\Domain\Enums\MerchantIdentityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveIdentityRequest extends FormRequest
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
            'id_type' => ['required', Rule::in(MerchantIdentityType::values())],
            'id_number' => ['required', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }
}
