<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\ProductDraftStep;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductDraft;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Storage\Contracts\ObjectStorage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeObjectStorage;

beforeEach(function () {
    $this->seedRbac();
});

function productDraftStorage(): ObjectStorage&FakeObjectStorage
{
    $storage = new FakeObjectStorage;
    app()->instance(ObjectStorage::class, $storage);

    return $storage;
}

/**
 * @return array{owner: User, merchant: Merchant, category: CatalogCategory}
 */
function productDraftMerchant(): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();

    return compact('owner', 'merchant', 'category');
}

function productDraftUrl(string $suffix = ''): string
{
    return '/api/v1/merchant/catalog/product-draft'.$suffix;
}

/**
 * A structurally valid wizard payload, ready to be tweaked per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productDraftPayload(array $overrides = []): array
{
    return [
        'info' => [
            'name' => 'Burger Spesial',
            'category_id' => '',
            'description' => '',
            'product_type' => 'simple',
        ],
        'price_raw' => '18000',
        'variants' => [],
        'modifier_groups' => [],
        'media' => [],
        'outlet_ids' => [],
        ...$overrides,
    ];
}

/**
 * A staged media entry pointing at a real object on the storage double.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stagedMediaEntry(FakeObjectStorage $storage, string $merchantId, array $overrides = []): array
{
    $objectKey = $overrides['object_key']
        ?? 'merchants/'.$merchantId.'/drafts/'.Str::uuid().'.jpg';

    $storage->register($objectKey, 2048);

    return [
        'key' => 'med-'.Str::uuid(),
        'object_key' => $objectKey,
        'file_name' => 'burger.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 2048,
        'alt_text' => '',
        'is_primary' => true,
        ...$overrides,
    ];
}

it('requires authentication for the product draft', function () {
    productDraftMerchant();

    $this->getJson(productDraftUrl())->assertStatus(401);
    $this->putJson(productDraftUrl(), [])->assertStatus(401);
    $this->deleteJson(productDraftUrl())->assertStatus(401);
});

it('returns no content when the merchant has no draft', function () {
    ['owner' => $owner] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $this->getJson(productDraftUrl())->assertStatus(204);
});

it('creates a draft on the first save and stamps a fresh expiry', function () {
    ['owner' => $owner, 'merchant' => $merchant, 'category' => $category] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $before = now();

    $this->putJson(productDraftUrl(), [
        'step_index' => ProductDraftStep::Price->value,
        'data' => productDraftPayload(['info' => [
            'name' => 'Burger Spesial',
            'category_id' => $category->id,
            'description' => 'Enak',
            'product_type' => 'simple',
        ]]),
    ])
        ->assertOk()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.step_index', 1)
        ->assertJsonPath('data.data.info.name', 'Burger Spesial')
        ->assertJsonPath('data.data.info.category_id', $category->id)
        ->assertJsonPath('data.data.price_raw', '18000');

    $draft = ProductDraft::query()->where('merchant_id', $merchant->id)->sole();

    expect($draft->version)->toBe(1)
        ->and($draft->expires_at->greaterThan($before->copy()->addDays(6)))->toBeTrue();
});

it('round trips the payload including draft media', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $media = [stagedMediaEntry($storage, $merchant->id), stagedMediaEntry($storage, $merchant->id)];

    $this->putJson(productDraftUrl(), [
        'step_index' => ProductDraftStep::Media->value,
        'data' => productDraftPayload([
            'media' => $media,
            'outlet_ids' => [(string) Str::uuid()],
            'modifier_groups' => [[
                'key' => 'grp-1',
                'name' => 'Level Pedas',
                'description' => '',
                'selection_type' => 'single',
                'min_selection' => 0,
                'max_selection' => null,
                'is_required' => false,
                'status' => 'active',
                'modifiers' => [[
                    'key' => 'mod-1',
                    'name' => 'Sedang',
                    'description' => '',
                    'price' => 0,
                    'is_default' => false,
                    'status' => 'active',
                ]],
            ]],
        ]),
    ])->assertOk();

    $response = $this->getJson(productDraftUrl())->assertOk();

    expect($response->json('data.data.media'))->toHaveCount(2)
        ->and($response->json('data.data.media.0.key'))->toBe($media[0]['key'])
        ->and($response->json('data.data.media.0.object_key'))->toBe($media[0]['object_key'])
        ->and($response->json('data.data.media.0.preview_url'))->toBe('https://storage.test/'.$media[0]['object_key'])
        ->and($response->json('data.data.modifier_groups.0.modifiers.0.name'))->toBe('Sedang')
        ->and($response->json('data.data.outlet_ids.0'))->toBeString();
});

it('bumps the version on every accepted save', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $this->putJson(productDraftUrl(), [
        'step_index' => 0,
        'data' => productDraftPayload(),
    ])->assertOk()->assertJsonPath('data.version', 1);

    $this->putJson(productDraftUrl(), [
        'expected_version' => 1,
        'step_index' => 1,
        'data' => productDraftPayload(),
    ])->assertOk()->assertJsonPath('data.version', 2);

    expect(ProductDraft::query()->where('merchant_id', $merchant->id)->sole()->step_index)->toBe(1);
});

it('refuses a save built on a stale version', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    ProductDraft::factory()->forMerchant($merchant->id)->atVersion(7)->create();

    $this->putJson(productDraftUrl(), [
        'expected_version' => 3,
        'step_index' => 2,
        'data' => productDraftPayload(),
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'product_draft_version_conflict')
        ->assertJsonPath('context.version', 7);
});

it('refuses a save that does not know a draft already exists', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    ProductDraft::factory()->forMerchant($merchant->id)->create();

    $this->putJson(productDraftUrl(), [
        'step_index' => 0,
        'data' => productDraftPayload(),
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'product_draft_version_conflict');
});

it('refuses a save whose draft was discarded elsewhere', function () {
    ['owner' => $owner] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $this->putJson(productDraftUrl(), [
        'expected_version' => 4,
        'step_index' => 0,
        'data' => productDraftPayload(),
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'product_draft_version_conflict');
});

it('rejects a draft with more media than a product may hold', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $limit = (int) config('storage.uploads.max_product_media');
    $media = [];

    for ($index = 0; $index <= $limit; $index++) {
        $media[] = stagedMediaEntry($storage, $merchant->id, ['is_primary' => $index === 0]);
    }

    $this->putJson(productDraftUrl(), [
        'step_index' => ProductDraftStep::Media->value,
        'data' => productDraftPayload(['media' => $media]),
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['data.media']]);
});

it('rejects a draft that exceeds the variant quota', function () {
    ['owner' => $owner] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $variants = [];
    $quota = (int) config('merchant.product_draft.max_variants');

    for ($index = 0; $index <= $quota; $index++) {
        $variants[] = [
            'key' => 'var-'.$index,
            'name' => 'Variant '.$index,
            'sku' => null,
            'price' => 1000,
            'status' => 'active',
            'is_default' => $index === 0,
        ];
    }

    $this->putJson(productDraftUrl(), [
        'step_index' => ProductDraftStep::Price->value,
        'data' => productDraftPayload(['variants' => $variants]),
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['data.variants']]);
});

it('rejects a step index outside the wizard', function () {
    ['owner' => $owner] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $this->putJson(productDraftUrl(), [
        'step_index' => 9,
        'data' => productDraftPayload(),
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['step_index']]);
});

it('accepts a draft whose category and outlets are not resolvable yet', function () {
    ['owner' => $owner] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $this->putJson(productDraftUrl(), [
        'step_index' => ProductDraftStep::Info->value,
        'data' => productDraftPayload([
            'info' => [
                'name' => '',
                'category_id' => '',
                'description' => '',
                'product_type' => 'simple',
            ],
            'outlet_ids' => [(string) Str::uuid()],
        ]),
    ])->assertOk();
});

it('discards an expired draft and its objects on read', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $entry = stagedMediaEntry($storage, $merchant->id);
    ProductDraft::factory()
        ->forMerchant($merchant->id)
        ->expired()
        ->withMedia([$entry])
        ->create();

    $this->getJson(productDraftUrl())->assertStatus(204);

    expect(ProductDraft::query()->where('merchant_id', $merchant->id)->exists())->toBeFalse()
        ->and($storage->deleted)->toBe([$entry['object_key']]);
});

it('discards an expired draft on save so a stale one is never resurrected', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    ProductDraft::factory()->forMerchant($merchant->id)->expired()->create();

    $this->putJson(productDraftUrl(), [
        'step_index' => 0,
        'data' => productDraftPayload(),
    ])->assertOk()->assertJsonPath('data.version', 1);
});

it('discards a draft and its unclaimed objects', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $entry = stagedMediaEntry($storage, $merchant->id);
    ProductDraft::factory()->forMerchant($merchant->id)->withMedia([$entry])->create();

    $this->deleteJson(productDraftUrl())->assertStatus(204);

    expect(ProductDraft::query()->where('merchant_id', $merchant->id)->exists())->toBeFalse()
        ->and($storage->deleted)->toBe([$entry['object_key']])
        ->and($this->getJson(productDraftUrl())->status())->toBe(204);
});

it('keeps objects a product has already claimed when the draft goes away', function () {
    ['owner' => $owner, 'merchant' => $merchant, 'category' => $category] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $entry = stagedMediaEntry($storage, $merchant->id);
    ProductDraft::factory()->forMerchant($merchant->id)->withMedia([$entry])->create();

    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    ProductMedia::factory()->forProduct($product)->create(['storage_key' => $entry['object_key']]);

    $this->deleteJson(productDraftUrl())->assertStatus(204);

    expect($storage->deleted)->toBe([])
        ->and($storage->exists($entry['object_key']))->toBeTrue();
});

it('discarding a draft that is already gone succeeds', function () {
    ['owner' => $owner] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $this->deleteJson(productDraftUrl())->assertStatus(204);
    $this->deleteJson(productDraftUrl())->assertStatus(204);
});

it('issues a draft upload url under the merchant draft prefix and opens a draft', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $response = $this->postJson(productDraftUrl('/media/upload-url'), [
        'file_name' => 'kopi.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
    ])
        ->assertOk()
        ->assertJsonPath('data.headers.Content-Type', 'image/jpeg');

    $objectKey = $response->json('data.object_key');

    expect($objectKey)->toStartWith('merchants/'.$merchant->id.'/drafts/')
        ->and($objectKey)->toEndWith('.jpg')
        ->and($response->json('data.preview_url'))->toBe('https://storage.test/'.$objectKey)
        ->and($response->json('data.expires_at'))->toBeString()
        ->and(ProductDraft::query()->where('merchant_id', $merchant->id)->exists())->toBeTrue();
});

it('rejects a non-image draft upload declaration', function () {
    ['owner' => $owner] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $this->postJson(productDraftUrl('/media/upload-url'), [
        'file_name' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['mime_type']]);
});

it('refuses a draft upload url when the media limit is reached', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $limit = (int) config('storage.uploads.max_product_media');
    $media = [];

    for ($index = 0; $index < $limit; $index++) {
        $media[] = stagedMediaEntry($storage, $merchant->id, ['is_primary' => $index === 0]);
    }

    ProductDraft::factory()->forMerchant($merchant->id)->withMedia($media)->create();

    $this->postJson(productDraftUrl('/media/upload-url'), [
        'file_name' => 'kopi.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1024,
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'media_limit_reached');
});

it('removes a staged photo and promotes the next one to primary', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $first = stagedMediaEntry($storage, $merchant->id, ['is_primary' => true]);
    $second = stagedMediaEntry($storage, $merchant->id, ['is_primary' => false]);

    $draft = ProductDraft::factory()
        ->forMerchant($merchant->id)
        ->withMedia([$first, $second])
        ->create();

    $this->deleteJson(productDraftUrl('/media'), ['object_key' => $first['object_key']])
        ->assertOk()
        ->assertJsonPath('data.version', $draft->version + 1)
        ->assertJsonPath('data.data.media.0.object_key', $second['object_key'])
        ->assertJsonPath('data.data.media.0.is_primary', true);

    expect($storage->deleted)->toBe([$first['object_key']]);
});

it('refuses to remove an object that is not staged on the draft', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $staged = stagedMediaEntry($storage, $merchant->id);
    $foreign = 'merchants/'.$merchant->id.'/drafts/'.Str::uuid().'.jpg';
    $storage->register($foreign, 2048);

    ProductDraft::factory()->forMerchant($merchant->id)->withMedia([$staged])->create();

    $this->deleteJson(productDraftUrl('/media'), ['object_key' => $foreign])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');

    $this->deleteJson(productDraftUrl('/media'), ['object_key' => 'merchants/'.Str::uuid().'/drafts/x.jpg'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');

    expect($storage->deleted)->toBe([]);
});

it('refuses to remove draft media when there is no draft', function () {
    ['owner' => $owner] = productDraftMerchant();
    productDraftStorage();
    Sanctum::actingAs($owner);

    $this->deleteJson(productDraftUrl('/media'), [
        'object_key' => 'merchants/'.Str::uuid().'/drafts/x.jpg',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');
});

it('keeps a draft whose object cleanup fails', function () {
    ['owner' => $owner, 'merchant' => $merchant] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $entry = stagedMediaEntry($storage, $merchant->id);
    $storage->unavailable[] = $entry['object_key'];

    ProductDraft::factory()->forMerchant($merchant->id)->withMedia([$entry])->create();

    $this->deleteJson(productDraftUrl())->assertStatus(204);

    expect(ProductDraft::query()->where('merchant_id', $merchant->id)->exists())->toBeFalse();
});

it('lets a product claim a photo staged in the merchant own draft', function () {
    ['owner' => $owner, 'merchant' => $merchant, 'category' => $category] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $entry = stagedMediaEntry($storage, $merchant->id);
    ProductDraft::factory()->forMerchant($merchant->id)->withMedia([$entry])->create();

    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    $this->postJson('/api/v1/merchant/catalog/products/'.$product->id.'/media', [
        'object_key' => $entry['object_key'],
        'alt_text' => 'Burger',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.is_primary', true);

    expect(ProductMedia::query()->where('product_id', $product->id)->sole()->storage_key)
        ->toBe($entry['object_key']);
});

it('refuses a product claim on a photo staged in another merchant draft', function () {
    ['owner' => $owner, 'merchant' => $merchant, 'category' => $category] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $otherOwner = test()->merchantUser();
    $otherMerchant = Merchant::factory()->forUser($otherOwner->id)->active()->create();

    $entry = stagedMediaEntry($storage, $otherMerchant->id);
    ProductDraft::factory()->forMerchant($otherMerchant->id)->withMedia([$entry])->create();

    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    $this->postJson('/api/v1/merchant/catalog/products/'.$product->id.'/media', [
        'object_key' => $entry['object_key'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');
});

it('refuses a product claim on a draft photo that was removed from the draft', function () {
    ['owner' => $owner, 'merchant' => $merchant, 'category' => $category] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $entry = stagedMediaEntry($storage, $merchant->id);
    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    // The object exists in storage but the draft no longer records it.
    $this->postJson('/api/v1/merchant/catalog/products/'.$product->id.'/media', [
        'object_key' => $entry['object_key'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');
});

it('keeps a full product submit from taking the draft objects with it', function () {
    ['owner' => $owner, 'merchant' => $merchant, 'category' => $category] = productDraftMerchant();
    $storage = productDraftStorage();
    Sanctum::actingAs($owner);

    $entry = stagedMediaEntry($storage, $merchant->id);
    ProductDraft::factory()->forMerchant($merchant->id)->withMedia([$entry])->create();

    $productResponse = $this->postJson('/api/v1/merchant/catalog/products', [
        'category_id' => $category->id,
        'name' => 'Burger Spesial',
        'product_type' => 'simple',
        'price' => 18000,
    ])->assertCreated();

    $productId = $productResponse->json('data.id');

    $this->postJson('/api/v1/merchant/catalog/products/'.$productId.'/media', [
        'object_key' => $entry['object_key'],
    ])->assertCreated();

    $this->deleteJson(productDraftUrl())->assertStatus(204);

    expect($storage->deleted)->toBe([])
        ->and($storage->exists($entry['object_key']))->toBeTrue()
        ->and(ProductMedia::query()->where('product_id', $productId)->exists())->toBeTrue();
});
