<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductDrafts;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

/**
 * Discards the merchant's draft, either because the wizard was abandoned or
 * because the product was finally created. Discarding something that is already
 * gone is a success so the client can retry the call safely.
 */
final class DeleteProductDraft
{
    use ManagesProductDrafts;

    public function __construct(
        private readonly ObjectStorage $storage,
    ) {}

    public function __invoke(Merchant $merchant): Result
    {
        $this->purgeExpiredDrafts($merchant, $this->storage);

        $draft = $this->activeDraft($merchant);

        if ($draft === null) {
            return Result::ok(null);
        }

        $this->discardDraft($draft, $this->storage);

        return Result::ok(null);
    }
}
