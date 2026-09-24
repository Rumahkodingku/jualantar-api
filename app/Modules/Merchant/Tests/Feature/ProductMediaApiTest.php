<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * In-memory object storage double: only the objects explicitly registered in
 * `$objects` exist, and `delete()` calls are recorded for assertions.
 */
function fakeCatalogObjectStorage(): ObjectStorage
{
    return new class implements ObjectStorage
    {
        /** @var array<string, int> */
        public array $objects = [];

        /** @var list<string> */
        public array $deleted = [];

        public string $mimeType = 'image/jpeg';

        public function put(string $path, string $contents, string $contentType): StoredObject
        {
            throw new LogicException('Not used in this test.');
        }

        public function putFile(string $path, UploadedFile $file): StoredObject
        {
            throw new LogicException('Not used in this test.');
        }

        public function exists(string $path): bool
        {
            return array_key_exists($path, $this->objects);
        }

        public function metadata(string $path): StoredObject
        {
            return new StoredObject($path, 'local', $this->objects[$path] ?? 0, $this->mimeType);
        }

        public function delete(string $path): void
        {
            $this->deleted[] = $path;
            unset($this->objects[$path]);
        }

        public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $options = []): string
        {
            return 'https://storage.test/'.$path;
        }

        public function temporaryUploadUrl(string $path, DateTimeInterface $expiresAt, string $contentType, array $options = []): TemporaryUpload
        {
            return new TemporaryUpload('https://upload.test/'.$path, ['Content-Type' => $contentType], $path, $expiresAt);
        }
    };
}

/**
 * @return array{owner: User, merchant: Merchant, product: Product}
 */
function catalogMediaProduct(): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    return compact('owner', 'merchant', 'product');
}

function mediaUrl(Product $product, string $suffix = ''): string
{
    return '/api/v1/merchant/catalog/products/'.$product->id.'/media'.$suffix;
}

/**
 * A server-shaped object key for this product, registered as existing.
 */
function registeredObjectKey(ObjectStorage $storage, Product $product, int $size = 2048): string
{
    $key = 'merchants/'.$product->merchant_id.'/products/'.$product->id.'/'.Str::uuid().'.jpg';
    $storage->objects[$key] = $size;

    return $key;
}

it('requires authentication for product media', function () {
    ['product' => $product] = catalogMediaProduct();

    $this->getJson(mediaUrl($product))->assertStatus(401);
});

it('issues a presigned upload url under the product prefix', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $storage = fakeCatalogObjectStorage();
    $this->app->instance(ObjectStorage::class, $storage);
    Sanctum::actingAs($owner);

    $response = $this->postJson(mediaUrl($product, '/upload-url'), [
        'file_name' => 'kopi.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
    ])
        ->assertOk()
        ->assertJsonPath('data.headers.Content-Type', 'image/jpeg');

    $objectKey = $response->json('data.object_key');

    expect($objectKey)->toStartWith('merchants/'.$product->merchant_id.'/products/'.$product->id.'/')
        ->and($objectKey)->toEndWith('.jpg')
        ->and($response->json('data.upload_url'))->toContain($objectKey)
        ->and($response->json('data.expires_at'))->toBeString();
});

it('rejects a non-image upload declaration', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product, '/upload-url'), [
        'file_name' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['mime_type']]);
});

it('rejects an oversize upload declaration', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product, '/upload-url'), [
        'file_name' => 'big.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => (int) config('storage.uploads.max_size') + 1,
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['file_size']]);
});

it('refuses to issue an upload url when the media limit is reached', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    ProductMedia::factory()->count(10)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product, '/upload-url'), [
        'file_name' => 'kopi.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'media_limit_reached');
});

it('registers media and makes the first one primary', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $storage = fakeCatalogObjectStorage();
    $this->app->instance(ObjectStorage::class, $storage);
    $objectKey = registeredObjectKey($storage, $product);
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product), [
        'object_key' => $objectKey,
        'alt_text' => 'Kopi Susu',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.is_primary', true)
        ->assertJsonPath('data.alt_text', 'Kopi Susu')
        ->assertJsonPath('data.mime_type', 'image/jpeg')
        ->assertJsonPath('data.file_size', 2048)
        ->assertJsonPath('data.display_order', 1);

    expect($storage->deleted)->toBe([]);
});

it('rejects an object key outside the product prefix', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $storage = fakeCatalogObjectStorage();
    $this->app->instance(ObjectStorage::class, $storage);
    $foreignKey = 'merchants/'.Str::uuid().'/products/'.Str::uuid().'/'.Str::uuid().'.jpg';
    $storage->objects[$foreignKey] = 1024;
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product), ['object_key' => $foreignKey])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');
});

it('rejects an object key that was never uploaded', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product), [
        'object_key' => 'merchants/'.$product->merchant_id.'/products/'.$product->id.'/missing.jpg',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');
});

it('rejects an object whose content type is not an image', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $storage = fakeCatalogObjectStorage();
    $storage->mimeType = 'application/pdf';
    $this->app->instance(ObjectStorage::class, $storage);
    $objectKey = registeredObjectKey($storage, $product);
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product), ['object_key' => $objectKey])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');
});

it('replaces the primary when storing with is_primary', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $storage = fakeCatalogObjectStorage();
    $this->app->instance(ObjectStorage::class, $storage);
    $first = ProductMedia::factory()->forProduct($product)->primary()->create();
    $objectKey = registeredObjectKey($storage, $product);
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product), ['object_key' => $objectKey, 'is_primary' => true])
        ->assertStatus(201)
        ->assertJsonPath('data.is_primary', true);

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and(ProductMedia::query()->where('is_primary', true)->count())->toBe(1);
});

it('refuses to register media beyond the limit', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $storage = fakeCatalogObjectStorage();
    $this->app->instance(ObjectStorage::class, $storage);
    ProductMedia::factory()->count(10)->forProduct($product)->create();
    $objectKey = registeredObjectKey($storage, $product);
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product), ['object_key' => $objectKey])
        ->assertStatus(409)
        ->assertJsonPath('code', 'media_limit_reached');

    expect($storage->deleted)->toBe([]);
});

it('lists media ordered by display order with a url', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    ProductMedia::factory()->forProduct($product)->create(['alt_text' => 'Kedua', 'display_order' => 2]);
    ProductMedia::factory()->forProduct($product)->create(['alt_text' => 'Pertama', 'display_order' => 1]);
    Sanctum::actingAs($owner);

    $response = $this->getJson(mediaUrl($product))
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.alt_text', 'Pertama')
        ->assertJsonPath('data.1.alt_text', 'Kedua');

    expect($response->json('data.0.url'))->toBeString()->not->toBeEmpty();
});

it('soft deletes media and promotes the next primary', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    $primary = ProductMedia::factory()->forProduct($product)->primary()->create(['display_order' => 1]);
    $next = ProductMedia::factory()->forProduct($product)->create(['display_order' => 2]);
    Sanctum::actingAs($owner);

    $this->deleteJson(mediaUrl($product, '/'.$primary->id))->assertStatus(204);

    expect(ProductMedia::query()->find($primary->id))->toBeNull()
        ->and(ProductMedia::withTrashed()->find($primary->id))->not->toBeNull()
        ->and($next->fresh()->is_primary)->toBeTrue();
});

it('leaves no primary when the last media is deleted', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    $only = ProductMedia::factory()->forProduct($product)->primary()->create();
    Sanctum::actingAs($owner);

    $this->deleteJson(mediaUrl($product, '/'.$only->id))->assertStatus(204);

    expect(ProductMedia::query()->where('is_primary', true)->count())->toBe(0);
});

it('does not delete the physical object when media is removed', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $storage = fakeCatalogObjectStorage();
    $this->app->instance(ObjectStorage::class, $storage);
    $objectKey = registeredObjectKey($storage, $product);
    $media = ProductMedia::factory()->forProduct($product)->create(['storage_key' => $objectKey]);
    Sanctum::actingAs($owner);

    $this->deleteJson(mediaUrl($product, '/'.$media->id))->assertStatus(204);

    expect($storage->deleted)->toBe([])
        ->and($storage->exists($objectKey))->toBeTrue();
});

it('swaps the primary media explicitly', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    $first = ProductMedia::factory()->forProduct($product)->primary()->create();
    $second = ProductMedia::factory()->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(mediaUrl($product, '/'.$second->id.'/primary'))
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and(ProductMedia::query()->where('is_primary', true)->count())->toBe(1);
});

it('reorders media inside the product', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    $first = ProductMedia::factory()->forProduct($product)->create(['display_order' => 1]);
    $second = ProductMedia::factory()->forProduct($product)->create(['display_order' => 2]);
    Sanctum::actingAs($owner);

    $this->putJson(mediaUrl($product, '/order'), [
        'items' => [
            ['media_id' => $first->id, 'display_order' => 2],
            ['media_id' => $second->id, 'display_order' => 1],
        ],
    ])->assertStatus(204);

    expect($first->fresh()->display_order)->toBe(2)
        ->and($second->fresh()->display_order)->toBe(1);
});

it('returns 404 for media of another product or merchant', function () {
    ['owner' => $owner, 'product' => $product] = catalogMediaProduct();
    $this->app->instance(ObjectStorage::class, fakeCatalogObjectStorage());
    $foreign = ProductMedia::factory()->create();
    Sanctum::actingAs($owner);

    $this->getJson(mediaUrl($product, '/'.$foreign->id))
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->deleteJson(mediaUrl($product, '/'.$foreign->id))->assertStatus(404);

    $otherProduct = Product::factory()->simple()->create();
    $this->getJson(mediaUrl($otherProduct))->assertStatus(404);
    $this->postJson(mediaUrl($otherProduct), ['object_key' => 'x'])->assertStatus(404);
});
