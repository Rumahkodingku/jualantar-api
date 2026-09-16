<?php

namespace App\Modules\Merchant\Http\Registration\Requests;

use App\Modules\Merchant\Http\Registration\Requests\Concerns\HasOutletRules;
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
