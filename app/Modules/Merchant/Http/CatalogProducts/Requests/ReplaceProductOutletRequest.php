<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Requests;

use App\Modules\Merchant\Http\CatalogProducts\Requests\Concerns\ResolvesOwnerMerchant;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceProductOutletRequest extends FormRequest
{
    use ResolvesOwnerMerchant;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The submitted list is the source of truth; an empty list detaches the
     * product from every outlet, so `present` is required but `min:1` is not.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'outlet_ids' => ['present', 'array', 'max:100'],
            'outlet_ids.*' => ['required', 'uuid', 'distinct', $this->ownedOutletExistsRule()],
        ];
    }
}
