<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Issues a presigned upload URL for a post-approval merchant asset. Unlike the
 * registration upload it never mutates the merchant; the returned object key is
 * attached later through the profile or outlet endpoints (which verify the key
 * prefix and existence).
 */
final class CreateOperationalUpload
{
    use ReportsOperationsErrors;

    public function __construct(
        private readonly MerchantOperationsAuthorization $authorization,
        private readonly ObjectStorage $storage,
    ) {}

    /**
     * @param  array{purpose: string, file_name: string, mime_type: string, file_size: int}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        $isLogo = $data['purpose'] === 'logo';

        $authorized = $isLogo
            ? $this->authorization->isOwner($merchant)
            : $this->authorization->hasOutletCapability('merchant.operations.outlets.update');

        if (! $authorized) {
            return $this->forbidden();
        }

        $folder = $isLogo ? 'logo' : 'outlets';
        $extension = pathinfo($data['file_name'], PATHINFO_EXTENSION);
        $assetId = (string) Str::uuid();
        $objectKey = "merchants/{$merchant->id}/{$folder}/{$assetId}".($extension === '' ? '' : ".{$extension}");

        $upload = $this->storage->temporaryUploadUrl(
            $objectKey,
            now()->addSeconds((int) config('storage.temporary_url.ttl', 300)),
            $data['mime_type'],
        );

        return Result::ok([
            'object_key' => $upload->path,
            'upload_url' => $upload->url,
            'headers' => $upload->headers,
            'expires_at' => $upload->expiresAt->format(DateTimeInterface::ATOM),
        ]);
    }
}
