<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use App\Modules\Merchant\Domain\Enums\OutletUserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignOutletUserRequest extends FormRequest
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
            'user_id' => ['required', 'uuid'],
            'role' => ['required', Rule::in(OutletUserRole::values())],
        ];
    }
}
