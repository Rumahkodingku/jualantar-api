<?php

namespace App\Modules\Merchant\Application\Registration\Actions;

use App\Modules\Merchant\Application\Registration\Concerns\GeneratesSlug;
use App\Modules\Merchant\Application\Registration\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;

final class UpdateRegistration
{
    use GeneratesSlug, ReportsRegistrationErrors;

    /**
     * @param  array{business_name?: string|null, type?: string|null, description?: string|null}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if ($error = $this->requireEditable($merchant)) {
            return $error;
        }

        $attributes = [];

        if (array_key_exists('business_name', $data)) {
            $attributes['business_name'] = $data['business_name'];
            $attributes['slug'] = filled($data['business_name'])
                ? $this->uniqueMerchantSlug($data['business_name'], $merchant->id)
                : null;
        }

        if (array_key_exists('type', $data)) {
            $attributes['type'] = $data['type'];
        }

        if (array_key_exists('description', $data)) {
            $attributes['description'] = $data['description'];
        }

        if ($attributes !== []) {
            $merchant->update($attributes);
        }

        return Result::ok($merchant->refresh());
    }
}
