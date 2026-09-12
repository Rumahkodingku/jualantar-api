<?php

namespace App\Modules\Storage\Contracts;

use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;

interface ObjectStorage
{
    public function put(string $path, string $contents, string $contentType): StoredObject;

    public function putFile(string $path, UploadedFile $file): StoredObject;

    public function exists(string $path): bool;

    public function metadata(string $path): StoredObject;

    public function delete(string $path): void;

    /**
     * @param  array<string, mixed>  $options
     */
    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $options = []): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function temporaryUploadUrl(
        string $path,
        DateTimeInterface $expiresAt,
        string $contentType,
        array $options = [],
    ): TemporaryUpload;
}
