<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Requests;

use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Http\CatalogProducts\Requests\Concerns\ResolvesOwnerMerchant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'uuid', $this->ownedCategoryExistsRule()],
            'description' => ['nullable', 'string'],
            'product_type' => ['required', Rule::in(ProductType::values())],
            'price' => [
                'required_if:product_type,'.ProductType::Simple->value,
                'nullable',
                'numeric',
                'min:0',
                'prohibited_if:product_type,'.ProductType::Variable->value,
            ],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['prohibited'],
        ];
    }
}
