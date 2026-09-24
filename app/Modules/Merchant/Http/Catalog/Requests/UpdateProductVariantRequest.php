<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Http\Catalog\Requests\Concerns\ResolvesOwnerMerchant;
use App\Modules\Merchant\Http\Catalog\Rules\UniqueProductVariantSku;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
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
        $variantId = $this->route('variant');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                new UniqueProductVariantSku(
                    $this->ownerMerchantId(),
                    is_string($variantId) ? $variantId : null,
                ),
            ],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['prohibited'],
        ];
    }
}
