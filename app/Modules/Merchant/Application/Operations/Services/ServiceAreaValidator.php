<?php

namespace App\Modules\Merchant\Application\Operations\Services;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Domain\Enums\OutletServiceAreaType;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

/**
 * Validates the service-area payload against the outlet's own region.
 *
 * P0 reuses the outlet address columns as the single source of truth, so a
 * non-radius service area is anchored to the outlet's own province/regency/
 * district/village. Fields that do not belong to the selected type are
 * rejected instead of silently ignored.
 */
final class ServiceAreaValidator
{
    /**
     * @var list<string>
     */
    private const REGION_FIELDS = [
        'province_id',
        'regency_id',
        'district_id',
        'village_id',
    ];

    public function __construct(private readonly GeographyLookup $geographyLookup) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function validate(MerchantOutlet $outlet, array $data): Result
    {
        $type = $data['type'] ?? null;

        if (! is_string($type) || ! in_array($type, OutletServiceAreaType::values(), true)) {
            return $this->invalid('The service area type is invalid.');
        }

        if ($type === OutletServiceAreaType::Radius->value) {
            return $this->validateRadius($data);
        }

        return $this->validateRegion($outlet, $type, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateRadius(array $data): Result
    {
        foreach (self::REGION_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                return $this->invalid("The {$field} field is not valid for a radius service area.");
            }
        }

        $radius = $data['radius_km'] ?? null;

        if (! is_numeric($radius)) {
            return $this->invalid('A radius service area requires radius_km.');
        }

        $radius = (float) $radius;

        if ($radius <= 0 || $radius > 999.99) {
            return $this->invalid('The radius_km must be between 0.1 and 999.99.');
        }

        return Result::ok([
            'type' => OutletServiceAreaType::Radius->value,
            'radius_km' => round($radius, 2),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateRegion(MerchantOutlet $outlet, string $type, array $data): Result
    {
        if (array_key_exists('radius_km', $data)) {
            return $this->invalid("The radius_km field is not valid for a {$type} service area.");
        }

        $field = $type.'_id';

        foreach (self::REGION_FIELDS as $regionField) {
            if ($regionField !== $field && array_key_exists($regionField, $data)) {
                return $this->invalid("The {$regionField} field is not valid for a {$type} service area.");
            }
        }

        if (! array_key_exists($field, $data) || ! is_numeric($data[$field])) {
            return $this->invalid("A {$type} service area requires {$field}.");
        }

        $regionId = (int) $data[$field];

        if ($regionId !== (int) $outlet->{$field}) {
            return $this->invalid("The {$type} service area must match the outlet's own region.");
        }

        if (! $this->geographyLookup->regionExists($type, $regionId)) {
            return $this->invalid('The selected region is invalid or inactive.');
        }

        return Result::ok(['type' => $type, 'radius_km' => null]);
    }

    private function invalid(string $message): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_service_area',
            message: $message,
            status: 422,
            title: 'Unprocessable Entity',
        ));
    }
}
