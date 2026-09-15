<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantDocument;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

final class DeleteMerchantDocument
{
    use ReportsRegistrationErrors;

    public function __construct(private readonly ObjectStorage $storage) {}

    public function __invoke(Merchant $merchant, MerchantDocument $document): Result
    {
        if ($error = $this->requireDraft($merchant)) {
            return $error;
        }

        if ($document->merchant_id !== $merchant->id) {
            return $this->registrationNotFound();
        }

        try {
            $this->storage->delete($document->object_key);
        } catch (\Throwable) {
            // The stored object may already be gone; the metadata is the source of truth.
        }

        $document->delete();

        return Result::ok(null);
    }
}
