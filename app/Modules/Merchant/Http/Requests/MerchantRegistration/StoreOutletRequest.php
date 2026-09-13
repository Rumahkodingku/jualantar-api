<?php

namespace App\Modules\Merchant\Http\Requests\MerchantRegistration;

use App\Modules\Merchant\Http\Requests\MerchantRegistration\Concerns\HasOutletRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreOutletRequest extends FormRequest
{
    use HasOutletRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->outletRules();
    }
}
