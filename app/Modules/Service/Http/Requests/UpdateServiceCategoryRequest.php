<?php

namespace App\Modules\Service\Http\Requests;

class UpdateServiceCategoryRequest extends StoreServiceCategoryRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'required', 'string'],
            'icon' => ['sometimes', 'required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
