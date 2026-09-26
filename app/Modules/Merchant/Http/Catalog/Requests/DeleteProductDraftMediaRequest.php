<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteProductDraftMediaRequest extends FormRequest
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
            'object_key' => ['required', 'string', 'max:1024'],
        ];
    }
}
