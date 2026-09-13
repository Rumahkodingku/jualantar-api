<?php

namespace App\Modules\Merchant\Http\Requests\MerchantRegistration;

use App\Modules\Merchant\Domain\Enums\LegalEntityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLegalEntityRequest extends FormRequest
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
            'entity_type' => ['required', Rule::in(LegalEntityType::values())],
            'name' => ['required', 'string', 'max:150'],
            'nib' => ['required', 'string', 'max:100'],
            'npwp' => ['required', 'string', 'max:25'],
            'address' => ['nullable', 'string'],
            'province_id' => ['required', 'integer'],
            'regency_id' => ['required', 'integer'],
            'district_id' => ['required', 'integer'],
            'village_id' => ['required', 'integer'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
