<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductDrafts;
use App\Modules\Merchant\Application\Catalog\Services\MediaUrlHydrator;
use App\Modules\Merchant\Domain\Enums\ProductMediaMimeType;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\ProductDraft;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Issues a presigned upload URL for a photo the merchant has staged in the
 * wizard. Uploading up front is what makes the draft survive a lost connection:
 * the bytes already exist when the draft is read back, and creating the product
 * only has to register the object key.
 *
 * The object key is minted by the server under the merchant's draft prefix, so
 * the client can never choose where the file lands. The draft itself is opened
 * on demand, because a merchant may pick a photo before saving any form state.
 */
final class CreateProductDraftMediaUploadUrl
{
    use ManagesProductDrafts;

    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly MediaUrlHydrator $mediaUrls,
    ) {}

    /**
     * @param  array{file_name: string, mime_type: string, file_size: int}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        $this->purgeExpiredDrafts($merchant, $this->storage);

        $draft = $this->activeDraft($merchant) ?? $this->openDraft($merchant);

        if ($this->draftMediaCount($draft) >= $this->draftMediaLimit()) {
            return $this->mediaLimitReached();
        }

        $mimeType = ProductMediaMimeType::from($data['mime_type']);

        $objectKey = sprintf(
            '%s%s.%s',
            $this->draftObjectKeyPrefix($merchant),
            (string) Str::uuid(),
            $mimeType->extension(),
        );

        $upload = $this->storage->temporaryUploadUrl(
            $objectKey,
            now()->addSeconds((int) config('storage.temporary_url.ttl', 300)),
            $mimeType->value,
        );

        return Result::ok([
            'object_key' => $upload->path,
            'upload_url' => $upload->url,
            'headers' => $upload->headers,
            'expires_at' => $upload->expiresAt->format(DateTimeInterface::ATOM),
            'preview_url' => $this->mediaUrls->url($objectKey),
        ]);
    }

    private function openDraft(Merchant $merchant): ProductDraft
    {
        return ProductDraft::query()->create([
            'merchant_id' => $merchant->id,
            'version' => 1,
            'step_index' => 0,
            'payload' => ProductDraft::EMPTY_PAYLOAD,
            'expires_at' => $this->draftFreshExpiry(),
        ]);
    }
}
