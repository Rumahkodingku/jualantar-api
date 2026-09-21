<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Application\Registration\Concerns\ManagesOutletPhotos;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

final class UpdateOutlet
{
    use ManagesOutletPhotos, ReportsOperationsErrors;

    public function __construct(
        private readonly GeographyLookup $geographyLookup,
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(MerchantOutlet $outlet, array $data): Result
    {
        if (
            array_key_exists('village_id', $data)
            && ! $this->geographyLookup->villageExists((int) $data['village_id'])
        ) {
            return $this->invalidGeography();
        }

        $photos = array_key_exists('photos', $data) ? array_values($data['photos'] ?? []) : null;

        if ($photos !== null && ($error = $this->validateOutletPhotos($this->storage, $outlet->merchant, $photos)) !== null) {
            return $error;
        }

        $previousPhotos = $outlet->photos ?? [];

        if ($photos !== null) {
            $data['photos'] = $photos;
        }

        $outlet->update($data);

        if ($photos !== null) {
            $this->deleteOutletPhotos($this->storage, array_values(array_diff($previousPhotos, $photos)));
        }

        return Result::ok($outlet->refresh());
    }
}
