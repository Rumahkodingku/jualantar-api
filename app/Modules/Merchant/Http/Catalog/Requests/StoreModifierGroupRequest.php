<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use App\Modules\Merchant\Http\Catalog\Requests\Concerns\ResolvesOwnerMerchant;
use App\Modules\Merchant\Http\Catalog\Rules\UniqueModifierGroupName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModifierGroupRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100',
                new UniqueModifierGroupName($this->ownerMerchantId(), $this->productId()),
            ],
            'description' => ['nullable', 'string'],
            'selection_type' => ['required', Rule::in(ModifierSelectionType::values())],
            'min_selection' => ['nullable', 'integer', 'min:0'],
            'max_selection' => ['nullable', 'integer', 'min:1'],
            'is_required' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['prohibited'],
        ];
    }

    private function productId(): ?string
    {
        $productId = $this->route('product');

        return is_string($productId) ? $productId : null;
    }
}
