<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductMedia;
use App\Modules\Merchant\Domain\Enums\ProductMediaMimeType;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Registers an already-uploaded object as product media. The object must sit
 * under this product's server-minted prefix and still exist in storage; its
 * size and content type are read from storage rather than trusted from the
 * client. If the database write fails the orphaned object is removed.
 */
final class CreateProductMedia
{
    use ManagesProductMedia;

    public function __construct(
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  array{object_key: string, is_primary?: bool|null, alt_text?: string|null, display_order?: int|null}  $data
     */
    public function __invoke(Product $product, array $data): Result
    {
        $objectKey = $data['object_key'];

        if (! $this->isOwnedObjectKey($product, $objectKey)) {
            return $this->catalogUploadInvalid('The upload does not belong to this product.');
        }

        if (! $this->storage->exists($objectKey)) {
            return $this->catalogUploadInvalid('The uploaded file could not be found.');
        }

        $metadata = $this->storage->metadata($objectKey);

        if (ProductMediaMimeType::tryFrom($metadata->mimeType) === null) {
            return $this->catalogUploadInvalid('Only JPEG, PNG and WebP images are accepted.');
        }

        if ($this->mediaCount($product) >= $this->mediaLimit()) {
            return $this->mediaLimitReached();
        }

        try {
            $media = DB::transaction(function () use ($product, $data, $objectKey, $metadata): ProductMedia {
                $isPrimary = $this->mediaCount($product) === 0
                    || (bool) ($data['is_primary'] ?? false);

                if ($isPrimary) {
                    $this->clearPrimary($product);
                }

                return ProductMedia::query()->create([
                    'merchant_id' => $product->merchant_id,
                    'product_id' => $product->id,
                    'storage_key' => $objectKey,
                    'mime_type' => $metadata->mimeType,
                    'file_size' => $metadata->size,
                    'alt_text' => $data['alt_text'] ?? null,
                    'is_primary' => $isPrimary,
                    'display_order' => $data['display_order'] ?? $this->nextDisplayOrder($product),
                ]);
            });
        } catch (Throwable $exception) {
            $this->storage->delete($objectKey);

            throw $exception;
        }

        return Result::ok($media);
    }
}
