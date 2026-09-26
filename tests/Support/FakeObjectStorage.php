<?php

namespace Tests\Support;

use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;

/**
 * In-memory object storage double: only the objects explicitly registered in
 * `$objects` exist, and `delete()` calls are recorded for assertions.
 *
 * Lives in the test namespace so module feature tests can register the objects
 * they need without the module boundary arch tests complaining about a module
 * importing another module's internals.
 */
final class FakeObjectStorage implements ObjectStorage
{
    /** @var array<string, int> */
    public array $objects = [];

    /** @var list<string> */
    public array $deleted = [];

    /**
     * Keys that should make `delete()` blow up, so a test can prove the caller
     * treats object cleanup as best effort.
     *
     * @var list<string>
     */
    public array $unavailable = [];

    public string $mimeType = 'image/jpeg';

    /**
     * Register an object as existing with the given size, returning its key.
     */
    public function register(string $path, int $size = 2048): string
    {
        $this->objects[$path] = $size;

        return $path;
    }

    public function put(string $path, string $contents, string $contentType): StoredObject
    {
        $size = strlen($contents);
        $this->objects[$path] = $size;

        return new StoredObject($path, 'fake', $size, $contentType);
    }

    public function putFile(string $path, UploadedFile $file): StoredObject
    {
        throw new \LogicException('Not used in these tests.');
    }

    public function exists(string $path): bool
    {
        return array_key_exists($path, $this->objects);
    }

    public function metadata(string $path): StoredObject
    {
        return new StoredObject($path, 'fake', $this->objects[$path] ?? 0, $this->mimeType);
    }

    public function delete(string $path): void
    {
        if (in_array($path, $this->unavailable, true)) {
            throw new \RuntimeException('The object store is unreachable.');
        }

        $this->deleted[] = $path;
        unset($this->objects[$path]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $options = []): string
    {
        return 'https://storage.test/'.$path;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function temporaryUploadUrl(
        string $path,
        DateTimeInterface $expiresAt,
        string $contentType,
        array $options = [],
    ): TemporaryUpload {
        return new TemporaryUpload('https://upload.test/'.$path, ['Content-Type' => $contentType], $path, $expiresAt);
    }
}
