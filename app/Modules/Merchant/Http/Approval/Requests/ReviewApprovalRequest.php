<?php

namespace App\Modules\Merchant\Http\Approval\Requests;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalReviewStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewApprovalRequest extends FormRequest
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
            'component' => ['required', Rule::in(MerchantApprovalComponent::values())],
            'subject_type' => ['required', Rule::in(MerchantApprovalSubjectType::values())],
            'subject_id' => ['required', 'uuid'],
            'status' => ['required', Rule::in([
                MerchantApprovalReviewStatus::Verified->value,
                MerchantApprovalReviewStatus::Rejected->value,
            ])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
