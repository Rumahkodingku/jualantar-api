<?php

namespace App\Modules\Merchant\Application\Concerns;

use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

trait ManagesOutletPhotos
{
    /**
     * Ensure every photo object key belongs to the merchant's outlet folder and
     * physically exists in storage.
     *
     * @param  list<string>  $photos
     */
    private function validateOutletPhotos(ObjectStorage $storage, Merchant $merchant, array $photos): ?Result
    {
        foreach ($photos as $photo) {
            if (! str_starts_with($photo, "merchants/{$merchant->id}/outlets/")) {
                return $this->uploadInvalid('The photo does not belong to this merchant.');
            }

            if (! $storage->exists($photo)) {
                return $this->uploadInvalid('The uploaded photo could not be found.');
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $photos
     */
    private function deleteOutletPhotos(ObjectStorage $storage, array $photos): void
    {
        foreach ($photos as $photo) {
            try {
                $storage->delete($photo);
            } catch (\Throwable) {
                // Storage cleanup failures must not block the domain mutation.
            }
        }
    }
}
