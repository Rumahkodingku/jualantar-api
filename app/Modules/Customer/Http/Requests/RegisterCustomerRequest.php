<?php

namespace App\Modules\Customer\Http\Requests;

use App\Shared\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Phone::normalize($this->input('phone'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30', Rule::unique('users', 'phone')],
            'username' => ['required', 'string', 'min:3', 'max:150', 'alpha_dash', Rule::unique('customers', 'username')],
            'full_name' => ['required', 'string', 'max:150'],
            'password' => ['required', 'confirmed', 'string', Password::min(8)->letters()->numbers()],
        ];
    }
}
