<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductMediaRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.media_id' => ['required', 'uuid', 'distinct'],
            'items.*.display_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
