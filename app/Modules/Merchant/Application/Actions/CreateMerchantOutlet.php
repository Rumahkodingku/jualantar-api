<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;

final class CreateMerchantOutlet
{
    use ReportsRegistrationErrors;

    public function __construct(private readonly GeographyLookup $geographyLookup) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if ($error = $this->requireDraft($merchant)) {
            return $error;
        }

        if (! $this->geographyLookup->villageExists((int) $data['village_id'])) {
            return $this->invalidGeography();
        }

        $outlet = $merchant->outlets()->create($data);

        return Result::ok($outlet);
    }
}
