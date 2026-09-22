<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use App\Modules\Merchant\Domain\Enums\OutletUserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreOutletEmployeeRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')],
            'password' => ['required', 'confirmed', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(OutletUserRole::values())],
        ];
    }
}
