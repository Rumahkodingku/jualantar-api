<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Requests;

use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Http\CatalogProducts\Requests\Concerns\ResolvesOwnerMerchant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    use ResolvesOwnerMerchant;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `product_type` is immutable and `status` is only changed through the
     * activate/deactivate endpoints, so both are rejected when sent.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category_id' => ['sometimes', 'required', 'uuid', $this->ownedCategoryExistsRule()],
            'description' => ['sometimes', 'nullable', 'string'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'price' => $this->productType() === ProductType::Variable
                ? ['prohibited']
                : ['sometimes', 'required', 'numeric', 'min:0'],
            'product_type' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    private function productType(): ?ProductType
    {
        $productId = $this->route('product');

        if (! is_string($productId)) {
            return null;
        }

        return Product::query()
            ->where('merchant_id', $this->ownerMerchantId())
            ->find($productId)?->product_type;
    }
}
