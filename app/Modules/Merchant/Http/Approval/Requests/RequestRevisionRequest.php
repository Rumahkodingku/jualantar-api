<?php

namespace App\Modules\Merchant\Http\Approval\Requests;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestRevisionRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component' => ['required', Rule::in(MerchantApprovalComponent::values())],
            'items.*.subject_type' => ['required', Rule::in(MerchantApprovalSubjectType::values())],
            'items.*.subject_id' => ['required', 'uuid'],
            'items.*.reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
