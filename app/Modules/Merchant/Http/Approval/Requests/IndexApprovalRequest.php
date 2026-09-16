<?php

namespace App\Modules\Merchant\Http\Approval\Requests;

use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndexApprovalRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(MerchantApplicationStatus::values())],
            'assignment' => [
                'nullable',
                'string',
                'max:36',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! in_array($value, ['me', 'unassigned'], true) && ! Str::isUuid((string) $value)) {
                        $fail('The selected assignment is invalid.');
                    }
                },
            ],
            'search' => ['nullable', 'string', 'max:150'],
            'sort' => ['nullable', Rule::in([
                'created_at',
                'updated_at',
                'assigned_at',
                'started_at',
                'completed_at',
            ])],
            'order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
