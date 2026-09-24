<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use App\Modules\Merchant\Http\Catalog\Requests\Concerns\ResolvesOwnerMerchant;
use App\Modules\Merchant\Http\Catalog\Rules\UniqueModifierGroupName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModifierGroupRequest extends FormRequest
{
    use ResolvesOwnerMerchant;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `status` is only changed through activate/deactivate, so it is rejected
     * when sent. Cross-field selection rules are enforced by the action against
     * the resulting state (existing record + payload).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $groupId = $this->route('group');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                new UniqueModifierGroupName(
                    $this->ownerMerchantId(),
                    $this->productId(),
                    is_string($groupId) ? $groupId : null,
                ),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'selection_type' => ['sometimes', 'required', Rule::in(ModifierSelectionType::values())],
            'min_selection' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_selection' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_required' => ['sometimes', 'nullable', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['prohibited'],
        ];
    }

    private function productId(): ?string
    {
        $productId = $this->route('product');

        return is_string($productId) ? $productId : null;
    }
}
