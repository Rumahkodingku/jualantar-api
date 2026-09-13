<?php

namespace App\Modules\Merchant\Http\Requests\MerchantRegistration;

use App\Modules\Merchant\Domain\Enums\MerchantDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachDocumentRequest extends FormRequest
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
            'document_type' => ['required', Rule::in(MerchantDocumentType::values())],
            'object_key' => ['required', 'string', 'max:500'],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'file_size' => ['required', 'integer', 'min:1'],
        ];
    }
}
