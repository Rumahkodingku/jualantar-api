<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMerchantOperationalProfileRequest extends FormRequest
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
            'business_name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'operational_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'operational_email' => ['sometimes', 'nullable', 'email', 'max:100'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }
}
