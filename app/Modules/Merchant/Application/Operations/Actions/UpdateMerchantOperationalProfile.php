<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Application\Registration\Concerns\GeneratesSlug;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

final class UpdateMerchantOperationalProfile
{
    use GeneratesSlug, ReportsOperationsErrors;

    public function __construct(private readonly ObjectStorage $storage) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        $attributes = [];

        if (array_key_exists('business_name', $data)) {
            $attributes['business_name'] = $data['business_name'];
            $attributes['slug'] = filled($data['business_name'])
                ? $this->uniqueMerchantSlug($data['business_name'], $merchant->id)
                : null;
        }

        foreach (['description', 'operational_phone', 'operational_email', 'website'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('logo', $data) && $data['logo'] !== null) {
            if ($error = $this->validateLogo($merchant, $data['logo'])) {
                return $error;
            }

            $attributes['logo'] = $data['logo'];
        }

        if ($attributes !== []) {
            $merchant->update($attributes);
        }

        return Result::ok($merchant->refresh());
    }

    private function validateLogo(Merchant $merchant, string $objectKey): ?Result
    {
        if (! str_starts_with($objectKey, "merchants/{$merchant->id}/logo/")) {
            return $this->uploadInvalid('The logo does not belong to this merchant.');
        }

        if (! $this->storage->exists($objectKey)) {
            return $this->uploadInvalid('The uploaded logo could not be found.');
        }

        return null;
    }
}
