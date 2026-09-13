<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\GeneratesMerchantSlug;
use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;

final class UpdateMerchantRegistration
{
    use GeneratesMerchantSlug, ReportsRegistrationErrors;

    /**
     * @param  array{business_name?: string|null, type?: string|null, description?: string|null}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if ($error = $this->requireDraft($merchant)) {
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
