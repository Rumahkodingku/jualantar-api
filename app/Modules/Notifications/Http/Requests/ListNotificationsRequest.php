<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Domain\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'unread' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
            'type' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
            'priority' => ['nullable', Rule::in(NotificationPriority::values())],
            'include_expired' => ['nullable', Rule::in(['true', 'false', '1', '0', 1, 0, true, false])],
        ];
    }
}
