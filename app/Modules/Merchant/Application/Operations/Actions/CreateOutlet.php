<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Application\Registration\Concerns\ManagesOutletPhotos;
use App\Modules\Merchant\Domain\Enums\OutletServiceAreaType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

final class CreateOutlet
{
    use ManagesOutletPhotos, ReportsOperationsErrors;

    private const DEFAULT_RADIUS_KM = 5;

    public function __construct(
        private readonly GeographyLookup $geographyLookup,
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if (! $this->geographyLookup->villageExists((int) $data['village_id'])) {
            return $this->invalidGeography();
        }

        $photos = array_values($data['photos'] ?? []);

        if ($photos !== [] && ($error = $this->validateOutletPhotos($this->storage, $merchant, $photos)) !== null) {
            return $error;
        }

        $data['photos'] = $photos;
        $data['service_area_type'] = OutletServiceAreaType::Radius->value;
        $data['service_radius_km'] = self::DEFAULT_RADIUS_KM;
        $data['status'] = OutletStatus::Active;

        $outlet = $merchant->outlets()->create($data);

        return Result::ok($outlet);
    }
}
