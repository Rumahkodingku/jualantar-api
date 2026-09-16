<?php

namespace App\Modules\Merchant\Application\Registration\Actions;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Registration\Concerns\ManagesOutletPhotos;
use App\Modules\Merchant\Application\Registration\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

final class UpdateOutlet
{
    use ManagesOutletPhotos, ReportsRegistrationErrors;

    public function __construct(
        private readonly GeographyLookup $geographyLookup,
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Merchant $merchant, MerchantOutlet $outlet, array $data): Result
    {
        if ($error = $this->requireEditable($merchant)) {
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

        $photos = array_key_exists('photos', $data) ? array_values($data['photos'] ?? []) : null;

        if ($photos !== null && ($error = $this->validateOutletPhotos($this->storage, $merchant, $photos)) !== null) {
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
