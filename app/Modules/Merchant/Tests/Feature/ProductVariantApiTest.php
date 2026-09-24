<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\ProductType;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * @param  array<string, mixed>  $attributes
 * @return array{owner: User, merchant: Merchant, product: Product}
 */
function catalogVariantProduct(array $attributes = [], ?ProductType $type = ProductType::Variable): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();

    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
        'product_type' => $type,
        'price' => $type === ProductType::Simple ? 5000 : null,
        ...$attributes,
    ]);

    return compact('owner', 'merchant', 'product');
}

function variantsUrl(Product $product, string $suffix = ''): string
{
    return '/api/v1/merchant/catalog/products/'.$product->id.'/variants'.$suffix;
}

it('requires authentication for variants', function () {
    ['product' => $product] = catalogVariantProduct();

    $this->getJson(variantsUrl($product))->assertStatus(401);
});

it('rejects a variant on a simple product', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct(type: ProductType::Simple);
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product), ['name' => 'Strawberry', 'price' => 10000])
        ->assertStatus(422)
        ->assertJsonPath('code', 'variant_not_allowed_for_simple_product');
});

it('creates a variant that is active by default', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product), [
        'name' => 'Strawberry',
        'sku' => 'ICE-STRAWBERRY',
        'price' => 10000,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Strawberry')
        ->assertJsonPath('data.sku', 'ICE-STRAWBERRY')
        ->assertJsonPath('data.price', '10000.00')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_default', false)
        ->assertJsonPath('data.display_order', 1);
});

it('rejects a status in the variant create payload', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product), [
        'name' => 'Strawberry',
        'price' => 10000,
        'status' => 'inactive',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['status']]);
});

it('rejects a negative variant price', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product), ['name' => 'Strawberry', 'price' => -1])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['price']]);
});

it('rejects a duplicate sku case-insensitively within the merchant', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    ProductVariant::factory()->forProduct($product)->create(['sku' => 'ICE-STRAWBERRY']);
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product), ['name' => 'Chocolate', 'sku' => 'ice-strawberry', 'price' => 10000])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.sku.0', 'The sku has already been taken.');
});

it('allows the same sku in another merchant', function () {
    ProductVariant::factory()->create(['sku' => 'ICE-STRAWBERRY']);
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product), ['name' => 'Strawberry', 'sku' => 'ICE-STRAWBERRY', 'price' => 10000])
        ->assertStatus(201);
});

it('allows reusing a sku after the previous variant was deleted', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    $variant = ProductVariant::factory()->forProduct($product)->create(['sku' => 'ICE-STRAWBERRY']);
    $variant->delete();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product), ['name' => 'Strawberry', 'sku' => 'ICE-STRAWBERRY', 'price' => 10000])
        ->assertStatus(201);
});

it('lists variants filtered by status', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Strawberry']);
    ProductVariant::factory()->forProduct($product)->inactive()->create(['name' => 'Chocolate']);
    Sanctum::actingAs($owner);

    $this->getJson(variantsUrl($product))
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');

    $this->getJson(variantsUrl($product).'?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Chocolate');

    $this->getJson(variantsUrl($product).'?search=straw')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Strawberry');
});

it('keeps only one default variant per product', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    $first = ProductVariant::factory()->forProduct($product)->default()->create(['name' => 'Strawberry']);
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product), [
        'name' => 'Chocolate',
        'price' => 10000,
        'is_default' => true,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.is_default', true);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and(ProductVariant::query()->where('is_default', true)->count())->toBe(1);
});

it('promotes another variant to default through update', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    $first = ProductVariant::factory()->forProduct($product)->default()->create();
    $second = ProductVariant::factory()->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->patchJson(variantsUrl($product).'/'.$second->id, ['is_default' => true])
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('refuses making an inactive variant the default', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    $variant = ProductVariant::factory()->forProduct($product)->inactive()->create();
    Sanctum::actingAs($owner);

    $this->patchJson(variantsUrl($product).'/'.$variant->id, ['is_default' => true])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('protects the last active variant of an active product', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct(['status' => 'active']);
    $variant = ProductVariant::factory()->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product).'/'.$variant->id.'/deactivate')
        ->assertStatus(409)
        ->assertJsonPath('code', 'last_active_variant');

    $this->deleteJson(variantsUrl($product).'/'.$variant->id)
        ->assertStatus(409)
        ->assertJsonPath('code', 'last_active_variant');

    expect($variant->fresh()->status->value)->toBe('active');
});

it('allows removing the last active variant while the product is inactive', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    $variant = ProductVariant::factory()->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product).'/'.$variant->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');
});

it('allows deactivating a variant when another active variant remains', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct(['status' => 'active']);
    $first = ProductVariant::factory()->forProduct($product)->create();
    ProductVariant::factory()->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product).'/'.$first->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');
});

it('resets the default flag when the default variant is deactivated without promoting another', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct(['status' => 'active']);
    $first = ProductVariant::factory()->forProduct($product)->default()->create();
    $second = ProductVariant::factory()->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(variantsUrl($product).'/'.$first->id.'/deactivate')->assertOk();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeFalse();
});

it('reorders variants inside the product', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    $first = ProductVariant::factory()->forProduct($product)->create(['display_order' => 1]);
    $second = ProductVariant::factory()->forProduct($product)->create(['display_order' => 2]);
    Sanctum::actingAs($owner);

    $this->putJson(variantsUrl($product, '/order'), [
        'items' => [
            ['variant_id' => $first->id, 'display_order' => 2],
            ['variant_id' => $second->id, 'display_order' => 1],
        ],
    ])->assertStatus(204);

    expect($first->fresh()->display_order)->toBe(2)
        ->and($second->fresh()->display_order)->toBe(1);
});

it('rejects reorder variants from another product', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    $own = ProductVariant::factory()->forProduct($product)->create();
    $foreign = ProductVariant::factory()->create();
    Sanctum::actingAs($owner);

    $this->putJson(variantsUrl($product, '/order'), [
        'items' => [
            ['variant_id' => $own->id, 'display_order' => 1],
            ['variant_id' => $foreign->id, 'display_order' => 2],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('returns 404 for a variant of another product', function () {
    ['owner' => $owner, 'product' => $product] = catalogVariantProduct();
    $foreign = ProductVariant::factory()->create();
    Sanctum::actingAs($owner);

    $this->getJson(variantsUrl($product).'/'.$foreign->id)
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->deleteJson(variantsUrl($product).'/'.$foreign->id)->assertStatus(404);
});

it('returns 404 for variants of another merchant product', function () {
    ['owner' => $owner] = catalogVariantProduct();
    $foreign = Product::factory()->variable()->create();
    Sanctum::actingAs($owner);

    $this->getJson(variantsUrl($foreign))->assertStatus(404)->assertJsonPath('code', 'not_found');
    $this->postJson(variantsUrl($foreign), ['name' => 'X', 'price' => 1000])->assertStatus(404);
});
