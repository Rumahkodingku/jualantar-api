<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductDrafts;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\ProductDraft;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;
use Throwable;

/**
 * Removes a staged photo from the draft and forgets the object behind it.
 *
 * The payload entry has to go as well: staged objects are proven to belong to
 * the merchant by being recorded on the draft, so leaving the entry behind
 * would let a client claim a key whose bytes no longer exist.
 */
final class DeleteProductDraftMedia
{
    use ManagesProductDrafts;

    public function __construct(
        private readonly ObjectStorage $storage,
    ) {}

    public function __invoke(Merchant $merchant, string $objectKey): Result
    {
        $this->purgeExpiredDrafts($merchant, $this->storage);

        $draft = $this->activeDraft($merchant);

        if ($draft === null) {
            return $this->catalogUploadInvalid('The upload does not belong to this draft.');
        }

        if (! $this->isOwnedDraftObjectKey($merchant, $objectKey)) {
            return $this->catalogUploadInvalid('The upload does not belong to this draft.');
        }

        try {
            $this->storage->delete($objectKey);
        } catch (Throwable) {
            // The payload edit below is what makes the draft consistent; a
            // stranded object is recoverable, a dangling key is not.
        }

        $draft->update([
            'payload' => $this->payloadWithout($draft, $objectKey),
            'version' => $draft->version + 1,
        ]);

        return Result::ok($draft->refresh());
    }

    /**
     * Drop the removed entry and make sure exactly the first remaining photo
     * carries the primary flag, so the wizard never restores a draft whose
     * only primary photo is gone.
     *
     * @return array<string, mixed>
     */
    private function payloadWithout(ProductDraft $draft, string $objectKey): array
    {
        $remaining = array_values(array_filter(
            $draft->mediaEntries(),
            fn (array $entry): bool => ($entry['object_key'] ?? null) !== $objectKey,
        ));

        foreach ($remaining as $index => $entry) {
            $remaining[$index] = [...$entry, 'is_primary' => $index === 0];
        }

        return [...$draft->payload, 'media' => $remaining];
    }
}
