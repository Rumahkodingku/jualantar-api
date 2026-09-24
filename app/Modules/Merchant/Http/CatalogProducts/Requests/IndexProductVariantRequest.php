<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Requests;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Http\CatalogProducts\Requests\Concerns\ResolvesOwnerMerchant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductVariantRequest extends FormRequest
{
    use ResolvesOwnerMerchant;

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
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(CatalogStatus::values())],
            'sort' => ['nullable', Rule::in(['name', 'price', 'display_order', 'created_at'])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
