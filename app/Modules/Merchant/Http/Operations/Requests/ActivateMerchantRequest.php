<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateMerchantRequest extends FormRequest
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
        return [];
    }
}
