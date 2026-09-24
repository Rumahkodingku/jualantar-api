<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductMedia;
use App\Modules\Merchant\Domain\Enums\ProductMediaMimeType;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Issues a presigned upload URL for product media. The object key is minted by
 * the server, namespaced per merchant and product, so the client can never
 * choose where the file lands.
 */
final class CreateProductMediaUploadUrl
{
    use ManagesProductMedia;

    public function __construct(
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  array{file_name: string, mime_type: string, file_size: int}  $data
     */
    public function __invoke(Product $product, array $data): Result
    {
        if ($this->mediaCount($product) >= $this->mediaLimit()) {
            return $this->mediaLimitReached();
        }

        $mimeType = ProductMediaMimeType::from($data['mime_type']);

        $objectKey = sprintf(
            '%s%s.%s',
            $this->objectKeyPrefix($product),
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
        ]);
    }
}
