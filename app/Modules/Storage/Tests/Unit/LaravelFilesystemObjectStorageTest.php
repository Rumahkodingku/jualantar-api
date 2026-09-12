<?php

use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Modules\Storage\Domain\Exceptions\ObjectNotFoundException;
use App\Modules\Storage\Domain\Exceptions\StorageException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['storage.default_disk' => 'local']);
    Storage::fake('local');
});

it('stores string contents and returns the stored object', function () {
    $object = app(ObjectStorage::class)->put('merchants/123/documents/abc', 'hello', 'text/plain');

    expect($object)->toBeInstanceOf(StoredObject::class)
        ->and($object->path)->toBe('merchants/123/documents/abc')
        ->and($object->disk)->toBe('local')
        ->and($object->size)->toBe(5)
        ->and($object->mimeType)->toBe('text/plain')
        ->and($object->etag)->toBeNull();

    Storage::disk('local')->assertExists('merchants/123/documents/abc');
});

it('stores an uploaded file and preserves its metadata', function () {
    $file = UploadedFile::fake()->create('ktp.pdf', 10, 'application/pdf');

    $object = app(ObjectStorage::class)->putFile('merchants/123/documents/abc', $file);

    expect($object->path)->toBe('merchants/123/documents/abc')
        ->and($object->mimeType)->toBe('application/pdf')
        ->and($object->size)->toBe(10240);

    Storage::disk('local')->assertExists('merchants/123/documents/abc');
});

it('checks object existence', function () {
    $storage = app(ObjectStorage::class);

    expect($storage->exists('merchants/123/documents/abc'))->toBeFalse();

    $storage->put('merchants/123/documents/abc', 'hello', 'text/plain');

    expect($storage->exists('merchants/123/documents/abc'))->toBeTrue();
});

it('reads object metadata', function () {
    $storage = app(ObjectStorage::class);
    $storage->put('merchants/123/documents/abc', 'hello world', 'text/plain');

    $object = $storage->metadata('merchants/123/documents/abc');

    expect($object->path)->toBe('merchants/123/documents/abc')
        ->and($object->disk)->toBe('local')
        ->and($object->size)->toBe(11)
        ->and($object->mimeType)->toBeString()->not->toBeEmpty()
        ->and($object->etag)->toBeString()->not->toBeEmpty();
});

it('throws when reading metadata for a missing object', function () {
    app(ObjectStorage::class)->metadata('merchants/123/documents/missing');
})->throws(ObjectNotFoundException::class);

it('deletes an object', function () {
    $storage = app(ObjectStorage::class);
    $storage->put('merchants/123/documents/abc', 'hello', 'text/plain');

    $storage->delete('merchants/123/documents/abc');

    expect($storage->exists('merchants/123/documents/abc'))->toBeFalse();
});

it('generates a temporary download url', function () {
    $storage = app(ObjectStorage::class);
    $storage->put('merchants/123/documents/abc', 'hello', 'text/plain');

    $url = $storage->temporaryUrl('merchants/123/documents/abc', now()->addMinutes(5));

    expect($url)->toBeString()->toContain('expiration');
});

it('generates a temporary upload url with headers', function () {
    $expiresAt = now()->addMinutes(5);

    $upload = app(ObjectStorage::class)->temporaryUploadUrl(
        'merchants/123/documents/abc',
        $expiresAt,
        'application/pdf',
    );

    expect($upload)->toBeInstanceOf(TemporaryUpload::class)
        ->and($upload->url)->toBeString()->not->toBeEmpty()
        ->and($upload->path)->toBe('merchants/123/documents/abc')
        ->and($upload->headers)->toBeArray()
        ->and($upload->expiresAt)->toBe($expiresAt);
});

it('wraps provider failures into a storage exception', function () {
    config([
        'filesystems.disks.unsupported' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => false,
            'throw' => true,
        ],
        'storage.default_disk' => 'unsupported',
    ]);

    expect(fn () => app(ObjectStorage::class)->temporaryUrl('merchants/123/documents/abc', now()->addMinutes(5)))
        ->toThrow(StorageException::class);
});
