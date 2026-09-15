<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Concerns\ManagesOutletPhotos;
use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

final class CreateMerchantOutlet
{
    use ManagesOutletPhotos, ReportsRegistrationErrors;

    public function __construct(
        private readonly GeographyLookup $geographyLookup,
        private readonly ObjectStorage $storage,
    ) {}

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

        $photos = array_values($data['photos'] ?? []);

        if ($photos !== [] && ($error = $this->validateOutletPhotos($this->storage, $merchant, $photos)) !== null) {
            return $error;
        }

        $data['photos'] = $photos;

        $outlet = $merchant->outlets()->create($data);

        return Result::ok($outlet);
    }
}
