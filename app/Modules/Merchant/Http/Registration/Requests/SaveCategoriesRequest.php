<?php

namespace App\Modules\Merchant\Http\Registration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCategoriesRequest extends FormRequest
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
            'category_ids' => ['required', 'array', 'min:1', 'max:3'],
            'category_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
