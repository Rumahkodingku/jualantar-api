<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use App\Modules\Merchant\Http\Operations\Requests\Concerns\HasOperationalOutletRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreOperationalOutletRequest extends FormRequest
{
    use HasOperationalOutletRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->operationalOutletRules();
    }
}
