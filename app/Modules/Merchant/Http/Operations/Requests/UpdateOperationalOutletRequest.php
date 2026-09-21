<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use App\Modules\Merchant\Http\Operations\Requests\Concerns\HasOperationalOutletRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationalOutletRequest extends FormRequest
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
        return $this->partial($this->operationalOutletRules());
    }
}
