<?php

namespace App\Modules\Merchant\Http\Requests\MerchantRegistration\Concerns;

use App\Modules\Merchant\Domain\Enums\OutletServiceAreaType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use Illuminate\Validation\Rule;

trait HasOutletRules
{
    /**
     * @var list<string>
     */
    protected const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

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
        ];

        foreach (self::DAYS as $day) {
            $rules["operating_hours.$day"] = ['nullable', 'array'];
            $rules["operating_hours.$day.*.open"] = ['required', 'date_format:H:i'];
            $rules["operating_hours.$day.*.close"] = ['required', 'date_format:H:i'];
        }

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
