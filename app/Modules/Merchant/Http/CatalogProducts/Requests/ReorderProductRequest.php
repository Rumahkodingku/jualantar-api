<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductRequest extends FormRequest
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
            'items.*.product_id' => ['required', 'uuid', 'distinct'],
            'items.*.display_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
