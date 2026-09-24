<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * @return array{owner: User, merchant: Merchant}
 */
function catalogProductOwner(): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();

    return compact('owner', 'merchant');
}

const PRODUCTS_URL = '/api/v1/merchant/catalog/products';

it('requires authentication for products', function () {
    $this->getJson(PRODUCTS_URL)->assertStatus(401);
});

it('creates a simple product with a price', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Sanctum::actingAs($owner);

    $this->postJson(PRODUCTS_URL, [
        'name' => 'Es Teh',
        'category_id' => $category->id,
        'product_type' => 'simple',
        'price' => 5000,
        'description' => 'Es teh manis',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.product_type', 'simple')
        ->assertJsonPath('data.price', '5000.00')
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.display_order', 1);
});

it('creates a variable product without a price', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Sanctum::actingAs($owner);

    $this->postJson(PRODUCTS_URL, [
        'name' => 'Ice Cream',
        'category_id' => $category->id,
        'product_type' => 'variable',
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.product_type', 'variable')
        ->assertJsonPath('data.price', null);
});

it('requires a price for a simple product', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Sanctum::actingAs($owner);

    $this->postJson(PRODUCTS_URL, [
        'name' => 'Es Teh',
        'category_id' => $category->id,
        'product_type' => 'simple',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['price']]);
});

it('rejects a price for a variable product', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Sanctum::actingAs($owner);

    $this->postJson(PRODUCTS_URL, [
        'name' => 'Ice Cream',
        'category_id' => $category->id,
        'product_type' => 'variable',
        'price' => 10000,
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['price']]);
});

it('rejects a status in the create payload', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Sanctum::actingAs($owner);

    $this->postJson(PRODUCTS_URL, [
        'name' => 'Es Teh',
        'category_id' => $category->id,
        'product_type' => 'simple',
        'price' => 5000,
        'status' => 'active',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['status']]);
});

it('rejects a category owned by another merchant', function () {
    ['owner' => $owner] = catalogProductOwner();
    $foreign = CatalogCategory::factory()->create();
    Sanctum::actingAs($owner);

    $this->postJson(PRODUCTS_URL, [
        'name' => 'Es Teh',
        'category_id' => $foreign->id,
        'product_type' => 'simple',
        'price' => 5000,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('rejects product_type and status changes through update', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    Sanctum::actingAs($owner);

    $this->patchJson(PRODUCTS_URL.'/'.$product->id, ['product_type' => 'variable'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['product_type']]);

    $this->patchJson(PRODUCTS_URL.'/'.$product->id, ['status' => 'active'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['status']]);

    expect($product->fresh()->product_type)->toBe(ProductType::Simple)
        ->and($product->fresh()->status)->toBe(CatalogStatus::Inactive);
});

it('rejects a price on a variable product through update', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->variable()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    Sanctum::actingAs($owner);

    $this->patchJson(PRODUCTS_URL.'/'.$product->id, ['price' => 5000])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['price']]);
});

it('rejects clearing the price of a simple product', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    Sanctum::actingAs($owner);

    $this->patchJson(PRODUCTS_URL.'/'.$product->id, ['price' => null])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['price']]);
});

it('updates a simple product name, price and category', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $other = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    Sanctum::actingAs($owner);

    $this->patchJson(PRODUCTS_URL.'/'.$product->id, [
        'name' => 'Es Teh Jumbo',
        'price' => 7500,
        'category_id' => $other->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Es Teh Jumbo')
        ->assertJsonPath('data.price', '7500.00')
        ->assertJsonPath('data.category_id', $other->id);
});

it('activates a simple product and deactivates it again', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    Sanctum::actingAs($owner);

    $this->postJson(PRODUCTS_URL.'/'.$product->id.'/activate')
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->postJson(PRODUCTS_URL.'/'.$product->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');
});

it('refuses to activate a variable product without an active variant', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->variable()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    Sanctum::actingAs($owner);

    $this->postJson(PRODUCTS_URL.'/'.$product->id.'/activate')
        ->assertStatus(409)
        ->assertJsonPath('code', 'variable_product_requires_active_variant');

    ProductVariant::factory()->forProduct($product)->inactive()->create();

    $this->postJson(PRODUCTS_URL.'/'.$product->id.'/activate')
        ->assertStatus(409)
        ->assertJsonPath('code', 'variable_product_requires_active_variant');

    ProductVariant::factory()->forProduct($product)->active()->create();

    $this->postJson(PRODUCTS_URL.'/'.$product->id.'/activate')
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('soft deletes a product together with its variants, media and assignments', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $product = Product::factory()->variable()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $media = ProductMedia::factory()->forProduct($product)->create();
    $assignment = OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->deleteJson(PRODUCTS_URL.'/'.$product->id)->assertStatus(204);

    expect(Product::query()->find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull()
        ->and(ProductVariant::withTrashed()->find($variant->id)->deleted_at)->not->toBeNull()
        ->and(ProductMedia::withTrashed()->find($media->id)->deleted_at)->not->toBeNull()
        ->and(OutletProduct::withTrashed()->find($assignment->id)->deleted_at)->not->toBeNull();
});

it('filters and sorts the product list', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $other = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Product::factory()->variable()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id, 'name' => 'Ice Cream']);
    Product::factory()->simple()->create(['merchant_id' => $merchant->id, 'category_id' => $other->id, 'name' => 'Es Teh']);
    Product::factory()->simple()->create(['merchant_id' => $merchant->id, 'category_id' => $other->id, 'name' => 'Kopi', 'status' => CatalogStatus::Active]);
    Product::factory()->simple()->create(); // other merchant
    Sanctum::actingAs($owner);

    $this->getJson(PRODUCTS_URL)->assertOk()->assertJsonPath('meta.total', 3);

    $this->getJson(PRODUCTS_URL.'?product_type=variable')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ice Cream');

    $this->getJson(PRODUCTS_URL.'?status=active')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Kopi');

    $this->getJson(PRODUCTS_URL.'?category_id='.$category->id)
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ice Cream');

    $this->getJson(PRODUCTS_URL.'?search=teh')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Es Teh');

    $this->getJson(PRODUCTS_URL.'?sort=name&order=desc')
        ->assertOk()->assertJsonPath('data.0.name', 'Kopi');
});

it('shows a product with its category, variants and media', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Makanan']);
    $product = Product::factory()->variable()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Strawberry']);
    ProductMedia::factory()->forProduct($product)->primary()->create();
    Sanctum::actingAs($owner);

    $response = $this->getJson(PRODUCTS_URL.'/'.$product->id)
        ->assertOk()
        ->assertJsonPath('data.category.name', 'Makanan')
        ->assertJsonCount(1, 'data.variants')
        ->assertJsonPath('data.variants.0.name', 'Strawberry')
        ->assertJsonCount(1, 'data.media')
        ->assertJsonPath('data.media.0.is_primary', true);

    expect($response->json('data.media.0.url'))->toBeString()->not->toBeEmpty();
});

it('reorders only the submitted products', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $first = Product::factory()->simple()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id, 'display_order' => 1]);
    $second = Product::factory()->simple()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id, 'display_order' => 2]);
    Sanctum::actingAs($owner);

    $this->putJson(PRODUCTS_URL.'/order', [
        'items' => [
            ['product_id' => $first->id, 'display_order' => 5],
            ['product_id' => $second->id, 'display_order' => 4],
        ],
    ])->assertStatus(204);

    expect($first->fresh()->display_order)->toBe(5)
        ->and($second->fresh()->display_order)->toBe(4);
});

it('rejects reorder items outside the merchant scope', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogProductOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $own = Product::factory()->simple()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    $foreign = Product::factory()->simple()->create();
    Sanctum::actingAs($owner);

    $this->putJson(PRODUCTS_URL.'/order', [
        'items' => [
            ['product_id' => $own->id, 'display_order' => 1],
            ['product_id' => $foreign->id, 'display_order' => 2],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('returns 404 for a product owned by another merchant', function () {
    ['owner' => $owner] = catalogProductOwner();
    $foreign = Product::factory()->simple()->create();
    Sanctum::actingAs($owner);

    $this->getJson(PRODUCTS_URL.'/'.$foreign->id)->assertStatus(404)->assertJsonPath('code', 'not_found');
    $this->patchJson(PRODUCTS_URL.'/'.$foreign->id, ['name' => 'Hijack'])->assertStatus(404);
    $this->deleteJson(PRODUCTS_URL.'/'.$foreign->id)->assertStatus(404);
    $this->postJson(PRODUCTS_URL.'/'.$foreign->id.'/activate')->assertStatus(404);

    expect($foreign->fresh()->name)->not->toBe('Hijack');
});
