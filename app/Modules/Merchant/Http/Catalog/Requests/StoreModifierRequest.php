<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Http\Catalog\Rules\UniqueModifierName;
use Illuminate\Foundation\Http\FormRequest;

class StoreModifierRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', new UniqueModifierName($this->groupId())],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['prohibited'],
        ];
    }

    private function groupId(): ?string
    {
        $groupId = $this->route('group');

        return is_string($groupId) ? $groupId : null;
    }
}
