<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use App\Modules\Merchant\Domain\Enums\OutletServiceAreaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceAreaRequest extends FormRequest
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
            'type' => ['required', Rule::in(OutletServiceAreaType::values())],
            'radius_km' => ['nullable', 'numeric'],
            'province_id' => ['nullable', 'integer'],
            'regency_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'village_id' => ['nullable', 'integer'],
        ];
    }
}
