<?php

namespace App\Modules\Merchant\Http\Operations\Requests\Concerns;

trait HasOperationalOutletRules
{
    /**
     * General outlet fields only. Operating hours and service area have their
     * own endpoints; status is managed through activate/deactivate.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function operationalOutletRules(): array
    {
        return [
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
            'photos' => ['nullable', 'array'],
            'photos.*' => ['required', 'string', 'max:500'],
        ];
    }

    /**
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
