<?php

namespace App\Modules\Merchant\Http\Registration\Requests\Concerns;

use App\Modules\Merchant\Domain\Enums\OutletServiceAreaType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait HasOutletRules
{
    /**
     * An open day must carry open/close times. `required_if` cannot reference a
     * wildcard path reliably, so this is enforced per day after the base rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hours = $validator->getData()['operating_hours'] ?? null;

            if (! is_array($hours)) {
                return;
            }

            foreach ($hours as $day => $schedule) {
                if (! is_array($schedule) || ($schedule['is_open'] ?? false) !== true) {
                    continue;
                }

                if (! is_string($schedule['open'] ?? null) || $schedule['open'] === '') {
                    $validator->errors()->add(
                        "operating_hours.{$day}.open",
                        'The open time is required when the day is open.',
                    );
                }

                if (! is_string($schedule['close'] ?? null) || $schedule['close'] === '') {
                    $validator->errors()->add(
                        "operating_hours.{$day}.close",
                        'The close time is required when the day is open.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function outletRules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['required', 'string'],
            'province_id' => ['required', 'integer'],
            'regency_id' => ['required', 'integer'],
            'district_id' => ['required', 'integer'],
            'village_id' => ['required', 'integer'],
            'postal_code' => ['required', 'string', 'max:10'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'service_area_type' => ['required', Rule::in(OutletServiceAreaType::values())],
            'service_radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:999.99', 'required_if:service_area_type,radius'],
            'status' => ['sometimes', Rule::in(OutletStatus::values())],
            'operating_hours' => ['nullable', 'array'],
            'operating_hours.*.is_open' => ['required', 'boolean'],
            'operating_hours.*.open' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'date_format:H:i'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['required', 'string', 'max:500'],
        ];

        return $rules;
    }

    /**
     * Make every rule optional so PATCH only validates provided fields.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    protected function partial(array $rules): array
    {
        return array_map(
            fn (array $rule): array => array_merge(['sometimes'], $rule),
            $rules,
        );
    }
}
