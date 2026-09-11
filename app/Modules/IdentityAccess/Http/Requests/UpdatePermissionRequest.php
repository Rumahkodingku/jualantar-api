<?php

namespace App\Modules\IdentityAccess\Http\Requests;

use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends StorePermissionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $permission = $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)+$/',
                Rule::unique('permissions', 'name')->ignore($permission?->id),
            ],
        ];
    }
}
