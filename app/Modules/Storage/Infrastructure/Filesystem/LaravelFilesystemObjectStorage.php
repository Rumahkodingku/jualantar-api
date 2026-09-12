<?php

namespace App\Modules\Storage\Infrastructure\Filesystem;

use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Modules\Storage\Domain\Exceptions\ObjectNotFoundException;
use App\Modules\Storage\Domain\Exceptions\StorageException;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Throwable;

final class LaravelFilesystemObjectStorage implements ObjectStorage
{
    public function __construct(private readonly StorageDiskResolver $resolver) {}

    public function put(string $path, string $contents, string $contentType): StoredObject
    {
        $disk = $this->disk();

        $stored = $this->guard(
            fn (): bool => $disk->put($path, $contents, ['ContentType' => $contentType]) !== false,
            $path,
        );

        if (! $stored) {
            throw $this->operationFailed($path);
        }

        return new StoredObject(
            path: $path,
            disk: $this->diskName(),
            size: strlen($contents),
            mimeType: $contentType,
        );
    }

    public function putFile(string $path, UploadedFile $file): StoredObject
    {
        $disk = $this->disk();
        $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';

        $stored = $this->guard(
            fn (): bool => $disk->putFileAs(dirname($path), $file, basename($path), ['ContentType' => $mimeType]) !== false,
            $path,
        );

        if (! $stored) {
            throw $this->operationFailed($path);
        }

        return new StoredObject(
            path: $path,
            disk: $this->diskName(),
            size: (int) $file->getSize(),
            mimeType: $mimeType,
        );
    }

    public function exists(string $path): bool
    {
        $disk = $this->disk();

        return (bool) $this->guard(fn (): bool => $disk->exists($path), $path);
    }

    public function metadata(string $path): StoredObject
    {
        $disk = $this->disk();

        if (! $this->guard(fn (): bool => $disk->exists($path), $path)) {
            throw new ObjectNotFoundException($path);
        }

        $size = $this->guard(fn () => $disk->size($path), $path);
        $mimeType = $this->guard(fn () => $disk->mimeType($path), $path);

        return new StoredObject(
            path: $path,
            disk: $this->diskName(),
            size: is_int($size) ? $size : 0,
            mimeType: is_string($mimeType) ? $mimeType : 'application/octet-stream',
            etag: $this->checksum($disk, $path),
        );
    }

    public function delete(string $path): void
    {
        $disk = $this->disk();

        $this->guard(fn (): bool => $disk->delete($path), $path);
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $options = []): string
    {
        $disk = $this->disk();

        return (string) $this->guard(
            fn (): string => $disk->temporaryUrl($path, $expiresAt, $options),
            $path,
        );
    }

    public function temporaryUploadUrl(
        string $path,
        DateTimeInterface $expiresAt,
        string $contentType,
        array $options = [],
    ): TemporaryUpload {
        $disk = $this->disk();

        $result = $this->guard(
            fn (): array => $disk->temporaryUploadUrl($path, $expiresAt, ['ContentType' => $contentType] + $options),
            $path,
        );

        return new TemporaryUpload(
            url: (string) ($result['url'] ?? ''),
            headers: is_array($result['headers'] ?? null) ? $result['headers'] : [],
            path: $path,
            expiresAt: $expiresAt,
        );
    }

    private function disk(): FilesystemAdapter
    {
        return $this->resolver->resolve();
    }

    private function diskName(): string
    {
        return (string) config('storage.default_disk');
    }

    private function checksum(FilesystemAdapter $disk, string $path): ?string
    {
        try {
            $checksum = $disk->checksum($path);

            return is_string($checksum) ? $checksum : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function operationFailed(string $path): StorageException
    {
        return new StorageException(
            context: ['path' => $path, 'disk' => $this->diskName()],
        );
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $operation
     * @return TReturn
     */
    private function guard(callable $operation, string $path): mixed
    {
        try {
            return $operation();
        } catch (Throwable $e) {
            throw StorageException::fromThrowable($e, $path, $this->diskName());
        }
    }
}
