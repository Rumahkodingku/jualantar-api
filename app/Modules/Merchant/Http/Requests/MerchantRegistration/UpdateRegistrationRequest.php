<?php

namespace App\Modules\Merchant\Http\Requests\MerchantRegistration;

use App\Modules\Merchant\Domain\Enums\MerchantType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegistrationRequest extends FormRequest
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
            'business_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'type' => ['sometimes', 'nullable', Rule::in(MerchantType::values())],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
