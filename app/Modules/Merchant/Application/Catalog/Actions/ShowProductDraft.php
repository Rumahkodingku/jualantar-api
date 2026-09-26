<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductDrafts;
use App\Modules\Merchant\Application\Catalog\Services\MediaUrlHydrator;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\ProductDraft;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;

/**
 * Loads the merchant's resumable wizard state, or nothing when they have not
 * started one. Expired drafts are discarded here so a stale draft is never
 * handed back to a client.
 */
final class ShowProductDraft
{
    use ManagesProductDrafts;

    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly MediaUrlHydrator $mediaUrls,
    ) {}

    public function __invoke(Merchant $merchant): Result
    {
        $this->purgeExpiredDrafts($merchant, $this->storage);

        $draft = $this->activeDraft($merchant);

        if ($draft === null) {
            return Result::ok(null);
        }

        $this->attachPreviewUrls($draft);

        return Result::ok($draft);
    }

    /**
     * Signed preview URLs expire long before a draft does, so they are minted
     * per read rather than persisted in the payload.
     */
    private function attachPreviewUrls(ProductDraft $draft): void
    {
        $media = array_map(
            fn (array $entry): array => [
                ...$entry,
                'preview_url' => $this->mediaUrls->url($entry['object_key'] ?? ''),
            ],
            $draft->mediaEntries(),
        );

        $draft->setAttribute('payload', [...$draft->payload, 'media' => $media]);
    }
}
