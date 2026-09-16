<?php

namespace App\Modules\Merchant\Http\Registration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePayoutAccountRequest extends FormRequest
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
            'bank_id' => ['required', 'integer'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:150'],
        ];
    }
}
