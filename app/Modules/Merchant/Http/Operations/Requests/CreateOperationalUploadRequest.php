<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOperationalUploadRequest extends FormRequest
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
            'purpose' => ['required', Rule::in(['logo', 'outlet'])],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100', Rule::in(config('storage.uploads.allowed_mime_types'))],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.config('storage.uploads.max_size')],
        ];
    }
}
