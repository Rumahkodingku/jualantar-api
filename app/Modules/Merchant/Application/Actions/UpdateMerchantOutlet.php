<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Shared\Result\Result;

final class UpdateMerchantOutlet
{
    use ReportsRegistrationErrors;

    public function __construct(private readonly GeographyLookup $geographyLookup) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Merchant $merchant, MerchantOutlet $outlet, array $data): Result
    {
        if ($error = $this->requireDraft($merchant)) {
            return $error;
        }

        if ($outlet->merchant_id !== $merchant->id) {
            return $this->registrationNotFound();
        }

        if (
            array_key_exists('village_id', $data)
            && ! $this->geographyLookup->villageExists((int) $data['village_id'])
        ) {
            return $this->invalidGeography();
        }

        $outlet->update($data);

        return Result::ok($outlet->refresh());
    }
}
