<?php

namespace App\Modules\Merchant\Http\Operations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The payload is the schedule itself, keyed by day name.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            '*' => ['required', 'array'],
            '*.is_open' => ['required', 'boolean'],
            '*.open' => ['nullable', 'date_format:H:i'],
            '*.close' => ['nullable', 'date_format:H:i'],
        ];
    }
}
