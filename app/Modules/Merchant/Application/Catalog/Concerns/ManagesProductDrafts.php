<?php

namespace App\Modules\Merchant\Application\Catalog\Concerns;

use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\ProductDraft;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;
use DateTimeInterface;
use Throwable;

/**
 * Shared draft lifecycle: where draft objects live, which of them a client may
 * claim, and when a draft stops existing.
 */
trait ManagesProductDrafts
{
    use ReportsCatalogErrors;

    private function draftTtlDays(): int
    {
        return (int) config('merchant.product_draft.ttl_days', 7);
    }

    private function draftFreshExpiry(): DateTimeInterface
    {
        return now()->addDays($this->draftTtlDays());
    }

    /**
     * Draft object keys are minted by the server as
     * merchants/{merchant_id}/drafts/{uuid}.{ext}; anything outside that prefix
     * is rejected so a client can never attach a file it does not own.
     */
    private function draftObjectKeyPrefix(Merchant $merchant): string
    {
        return "merchants/{$merchant->id}/drafts/";
    }

    private function activeDraft(Merchant $merchant): ?ProductDraft
    {
        return ProductDraft::query()
            ->active()
            ->where('merchant_id', $merchant->id)
            ->first();
    }

    private function draftMediaLimit(): int
    {
        return (int) config('storage.uploads.max_product_media', 10);
    }

    private function draftMediaCount(ProductDraft $draft): int
    {
        return count($draft->mediaEntries());
    }

    /**
     * A staged object belongs to the merchant when it sits under their draft
     * prefix and is actually recorded on the draft. Requiring the payload entry
     * is what makes the check meaningful: the prefix alone is forgeable, a
     * server-stored entry is not.
     */
    private function isOwnedDraftObjectKey(Merchant $merchant, string $objectKey): bool
    {
        if (! str_starts_with($objectKey, $this->draftObjectKeyPrefix($merchant))) {
            return false;
        }

        $draft = $this->activeDraft($merchant);

        if ($draft === null) {
            return false;
        }

        return in_array($objectKey, $draft->mediaObjectKeys(), true);
    }

    /**
     * Drop the expired drafts of a merchant together with the objects they
     * staged. A draft is only ever read or written by its owner, so purging
     * lazily on access is enough and needs no scheduled job.
     */
    private function purgeExpiredDrafts(Merchant $merchant, ObjectStorage $storage): void
    {
        $expired = ProductDraft::query()
            ->expired()
            ->where('merchant_id', $merchant->id)
            ->get();

        foreach ($expired as $draft) {
            $this->discardDraft($draft, $storage);
        }
    }

    private function discardDraft(ProductDraft $draft, ObjectStorage $storage): void
    {
        $this->deleteUnclaimedObjects($draft, $storage);
        $draft->delete();
    }

    /**
     * Delete the staged objects that no product has claimed yet. Once a draft
     * media entry has been registered on a product it is permanent catalog data
     * and outlives the draft, so it is left alone.
     */
    private function deleteUnclaimedObjects(ProductDraft $draft, ObjectStorage $storage): void
    {
        foreach ($draft->mediaObjectKeys() as $objectKey) {
            if (ProductMedia::query()->withTrashed()->where('storage_key', $objectKey)->exists()) {
                continue;
            }

            try {
                $storage->delete($objectKey);
            } catch (Throwable) {
                // The draft row is authoritative; a failed object delete must
                // not stop the draft from being discarded.
            }
        }
    }

    private function draftVersionConflict(string $message, array $context = []): Result
    {
        return Result::err(new ResultError(
            code: 'product_draft_version_conflict',
            message: $message,
            status: 409,
            title: 'Conflict',
            context: $context,
        ));
    }
}
