<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateRoleRequest extends StoreRoleRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9-]*$/',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
        ];
    }
}
