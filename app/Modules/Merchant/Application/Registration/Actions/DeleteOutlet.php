<?php

namespace App\Modules\Merchant\Application\Registration\Actions;

use App\Modules\Merchant\Application\Registration\Concerns\ManagesOutletPhotos;
use App\Modules\Merchant\Application\Registration\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

final class DeleteOutlet
{
    use ManagesOutletPhotos, ReportsRegistrationErrors;

    public function __construct(private readonly ObjectStorage $storage) {}

    public function __invoke(Merchant $merchant, MerchantOutlet $outlet): Result
    {
        if ($error = $this->requireEditable($merchant)) {
            return $error;
        }

        if ($outlet->merchant_id !== $merchant->id) {
            return $this->registrationNotFound();
        }

        $photos = $outlet->photos ?? [];

        $outlet->delete();

        $this->deleteOutletPhotos($this->storage, $photos);

        return Result::ok(null);
    }
}
