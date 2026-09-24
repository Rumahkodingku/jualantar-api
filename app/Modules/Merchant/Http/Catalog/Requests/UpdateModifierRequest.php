<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Http\Catalog\Rules\UniqueModifierName;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `status` is only changed through activate/deactivate, and a modifier
     * cannot be moved to another group, so both are rejected when sent.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $modifierId = $this->route('modifier');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                new UniqueModifierName($this->groupId(), is_string($modifierId) ? $modifierId : null),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_default' => ['sometimes', 'nullable', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'status' => ['prohibited'],
            'modifier_group_id' => ['prohibited'],
        ];
    }

    private function groupId(): ?string
    {
        $groupId = $this->route('group');

        return is_string($groupId) ? $groupId : null;
    }
}
