<?php

namespace App\Modules\Merchant\Application\Registration\Actions;

use App\Modules\Merchant\Application\Registration\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Result\Result;
use DateTimeInterface;
use Illuminate\Support\Str;

final class CreateUpload
{
    use ReportsRegistrationErrors;

    public function __construct(private readonly ObjectStorage $storage) {}

    /**
     * @param  array{purpose: string, file_name: string, mime_type: string, file_size: int}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if ($error = $this->requireEditable($merchant)) {
            return $error;
        }

        $folder = match ($data['purpose']) {
            'logo' => 'logo',
            'outlet' => 'outlets',
            default => 'documents',
        };
        $extension = pathinfo($data['file_name'], PATHINFO_EXTENSION);
        $assetId = (string) Str::uuid();
        $objectKey = "merchants/{$merchant->id}/{$folder}/{$assetId}".($extension === '' ? '' : ".{$extension}");

        $upload = $this->storage->temporaryUploadUrl(
            $objectKey,
            now()->addSeconds((int) config('storage.temporary_url.ttl', 300)),
            $data['mime_type'],
        );

        if ($data['purpose'] === 'logo') {
            $merchant->update(['logo' => $objectKey]);
        }

        return Result::ok([
            'object_key' => $upload->path,
            'upload_url' => $upload->url,
            'headers' => $upload->headers,
            'expires_at' => $upload->expiresAt->format(DateTimeInterface::ATOM),
        ]);
    }
}
