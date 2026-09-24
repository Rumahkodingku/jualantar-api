<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexModifierGroupRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(CatalogStatus::values())],
            'sort' => ['nullable', Rule::in(['display_order', 'name', 'created_at'])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
