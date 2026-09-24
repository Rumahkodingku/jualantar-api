<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Requests;

use App\Modules\Merchant\Http\CatalogProducts\Requests\Concerns\ResolvesOwnerMerchant;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductOutletRequest extends FormRequest
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
            'outlet_ids' => ['required', 'array', 'min:1', 'max:100'],
            'outlet_ids.*' => ['required', 'uuid', 'distinct', $this->ownedOutletExistsRule()],
        ];
    }
}
