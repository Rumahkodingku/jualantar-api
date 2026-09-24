<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Http\Catalog\Requests\Concerns\ResolvesOwnerMerchant;
use App\Modules\Merchant\Http\Catalog\Rules\UniqueProductVariantSku;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                new UniqueProductVariantSku($this->ownerMerchantId()),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['prohibited'],
        ];
    }
}
