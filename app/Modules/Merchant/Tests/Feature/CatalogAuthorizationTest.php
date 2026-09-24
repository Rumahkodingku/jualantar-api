<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\OutletUserRole;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * A merchant with one full catalog graph, plus access to a second merchant used
 * for cross-tenant assertions.
 *
 * @return array<string, mixed>
 */
function authorizationFixture(): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->variable()->active()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $media = ProductMedia::factory()->forProduct($product)->primary()->create();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    $modifier = ProductModifier::factory()->forGroup($group)->create();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $otherOutlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    OutletProduct::factory()->forOutlet($otherOutlet)->forProduct($product)->create();

    $foreignOwner = test()->merchantUser();
    $foreignMerchant = Merchant::factory()->forUser($foreignOwner->id)->active()->create();
    $foreignCategory = CatalogCategory::factory()->forMerchant($foreignMerchant->id)->create();
    $foreignProduct = Product::factory()->variable()->active()->create([
        'merchant_id' => $foreignMerchant->id,
        'category_id' => $foreignCategory->id,
    ]);
    $foreignVariant = ProductVariant::factory()->forProduct($foreignProduct)->create();
    $foreignMedia = ProductMedia::factory()->forProduct($foreignProduct)->primary()->create();
    $foreignGroup = ProductModifierGroup::factory()->forProduct($foreignProduct)->create();
    $foreignModifier = ProductModifier::factory()->forGroup($foreignGroup)->create();
    $foreignOutlet = MerchantOutlet::factory()->create(['merchant_id' => $foreignMerchant->id]);
    OutletProduct::factory()->forOutlet($foreignOutlet)->forProduct($foreignProduct)->create();

    return compact(
        'owner', 'merchant', 'category', 'product', 'variant', 'media', 'group', 'modifier', 'outlet', 'otherOutlet',
        'foreignOwner', 'foreignMerchant', 'foreignCategory', 'foreignProduct', 'foreignVariant',
        'foreignMedia', 'foreignGroup', 'foreignModifier', 'foreignOutlet',
    );
}

/**
 * @return array{user: User, outlet: MerchantOutlet}
 */
function authorizationEmployee(Merchant $merchant, OutletUserRole $role, ?MerchantOutlet $outlet = null): array
{
    $outlet ??= MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $user = test()->plainUser();

    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($user->id)->create(['role' => $role]);

    return compact('user', 'outlet');
}

const CATALOG_PREFIX = '/api/v1/merchant/catalog';

/**
 * Every owner-only catalog action: method, path, payload.
 *
 * @param  array<string, mixed>  $f
 * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
 */
function ownerOnlyCatalogRequests(array $f): array
{
    $product = $f['product']->id;
    $variant = $f['variant']->id;
    $media = $f['media']->id;
    $group = $f['group']->id;
    $modifier = $f['modifier']->id;

    return [
        ['get', '/categories', []],
        ['post', '/categories', ['name' => 'Baru']],
        ['put', '/categories/order', ['items' => [['category_id' => $f['category']->id, 'display_order' => 1]]]],
        ['get', '/categories/'.$f['category']->id, []],
        ['patch', '/categories/'.$f['category']->id, ['name' => 'Ganti']],
        ['delete', '/categories/'.$f['category']->id, []],
        ['post', '/categories/'.$f['category']->id.'/activate', []],
        ['post', '/categories/'.$f['category']->id.'/deactivate', []],
        ['get', '/products', []],
        ['post', '/products', ['name' => 'Baru', 'category_id' => $f['category']->id, 'product_type' => 'simple', 'price' => 1000]],
        ['put', '/products/order', ['items' => [['product_id' => $product, 'display_order' => 1]]]],
        ['get', '/products/'.$product, []],
        ['patch', '/products/'.$product, ['name' => 'Ganti']],
        ['delete', '/products/'.$product, []],
        ['post', '/products/'.$product.'/activate', []],
        ['post', '/products/'.$product.'/deactivate', []],
        ['get', '/products/'.$product.'/variants', []],
        ['post', '/products/'.$product.'/variants', ['name' => 'V', 'price' => 1000]],
        ['put', '/products/'.$product.'/variants/order', ['items' => [['variant_id' => $variant, 'display_order' => 1]]]],
        ['get', '/products/'.$product.'/variants/'.$variant, []],
        ['patch', '/products/'.$product.'/variants/'.$variant, ['name' => 'Ganti']],
        ['delete', '/products/'.$product.'/variants/'.$variant, []],
        ['post', '/products/'.$product.'/variants/'.$variant.'/activate', []],
        ['post', '/products/'.$product.'/variants/'.$variant.'/deactivate', []],
        ['get', '/products/'.$product.'/media', []],
        ['post', '/products/'.$product.'/media/upload-url', ['file_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'file_size' => 100]],
        ['post', '/products/'.$product.'/media', ['object_key' => 'x']],
        ['put', '/products/'.$product.'/media/order', ['items' => [['media_id' => $media, 'display_order' => 1]]]],
        ['get', '/products/'.$product.'/media/'.$media, []],
        ['delete', '/products/'.$product.'/media/'.$media, []],
        ['post', '/products/'.$product.'/media/'.$media.'/primary', []],
        ['get', '/products/'.$product.'/outlets', []],
        ['post', '/products/'.$product.'/outlets', ['outlet_ids' => [$f['outlet']->id]]],
        ['put', '/products/'.$product.'/outlets', ['outlet_ids' => []]],
        ['delete', '/products/'.$product.'/outlets/'.$f['outlet']->id, []],
        ['get', '/products/'.$product.'/modifier-groups', []],
        ['post', '/products/'.$product.'/modifier-groups', ['name' => 'Grup', 'selection_type' => 'single']],
        ['put', '/products/'.$product.'/modifier-groups/order', ['items' => [['group_id' => $group, 'display_order' => 1]]]],
        ['get', '/products/'.$product.'/modifier-groups/'.$group, []],
        ['patch', '/products/'.$product.'/modifier-groups/'.$group, ['name' => 'Ganti']],
        ['delete', '/products/'.$product.'/modifier-groups/'.$group, []],
        ['post', '/products/'.$product.'/modifier-groups/'.$group.'/activate', []],
        ['post', '/products/'.$product.'/modifier-groups/'.$group.'/deactivate', []],
        ['get', '/products/'.$product.'/modifier-groups/'.$group.'/modifiers', []],
        ['post', '/products/'.$product.'/modifier-groups/'.$group.'/modifiers', ['name' => 'Mod', 'price' => 0]],
        ['put', '/products/'.$product.'/modifier-groups/'.$group.'/modifiers/order', ['items' => [['modifier_id' => $modifier, 'display_order' => 1]]]],
        ['get', '/products/'.$product.'/modifier-groups/'.$group.'/modifiers/'.$modifier, []],
        ['patch', '/products/'.$product.'/modifier-groups/'.$group.'/modifiers/'.$modifier, ['name' => 'Ganti']],
        ['delete', '/products/'.$product.'/modifier-groups/'.$group.'/modifiers/'.$modifier, []],
        ['post', '/products/'.$product.'/modifier-groups/'.$group.'/modifiers/'.$modifier.'/activate', []],
        ['post', '/products/'.$product.'/modifier-groups/'.$group.'/modifiers/'.$modifier.'/deactivate', []],
    ];
}

it('requires authentication for every catalog endpoint', function () {
    $f = authorizationFixture();

    foreach (ownerOnlyCatalogRequests($f) as [$method, $path, $payload]) {
        $this->json($method, CATALOG_PREFIX.$path, $payload)
            ->assertStatus(401)
            ->assertJsonPath('code', 'unauthenticated');
    }

    $this->getJson(CATALOG_PREFIX.'/outlets/'.$f['outlet']->id.'/products')->assertStatus(401);
    $this->putJson(CATALOG_PREFIX.'/outlets/'.$f['outlet']->id.'/products/order', ['items' => []])->assertStatus(401);
});

it('requires a merchant context for catalog endpoints', function () {
    Sanctum::actingAs($this->plainUser());

    $this->getJson(CATALOG_PREFIX.'/categories')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('restricts every owner-only action to the merchant owner', function () {
    $f = authorizationFixture();
    $requests = ownerOnlyCatalogRequests($f);

    foreach ([OutletUserRole::OutletManager, OutletUserRole::OutletStaff] as $role) {
        $employee = authorizationEmployee($f['merchant'], $role, $f['outlet']);
        Sanctum::actingAs($employee['user']);

        foreach ($requests as [$method, $path, $payload]) {
            $response = $this->json($method, CATALOG_PREFIX.$path, $payload);

            expect($response->getStatusCode())->toBe(403, "{$role->value} {$method} {$path}")
                ->and($response->json('code'))->toBe('forbidden', "{$role->value} {$method} {$path}");
        }
    }
});

it('grants the owner-only actions to the merchant owner', function () {
    $f = authorizationFixture();
    Sanctum::actingAs($f['owner']);

    $this->getJson(CATALOG_PREFIX.'/categories')->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products')->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products/'.$f['product']->id)->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products/'.$f['product']->id.'/variants')->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products/'.$f['product']->id.'/media')->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products/'.$f['product']->id.'/outlets')->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products/'.$f['product']->id.'/modifier-groups')->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products/'.$f['product']->id.'/modifier-groups/'.$f['group']->id)->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products/'.$f['product']->id.'/modifier-groups/'.$f['group']->id.'/modifiers')->assertOk();
    $this->getJson(CATALOG_PREFIX.'/products/'.$f['product']->id.'/modifier-groups/'.$f['group']->id.'/modifiers/'.$f['modifier']->id)->assertOk();
});

it('resolves every catalog resource of another merchant as not found', function () {
    $f = authorizationFixture();
    Sanctum::actingAs($f['owner']);

    $notFound = [
        ['get', '/categories/'.$f['foreignCategory']->id],
        ['patch', '/categories/'.$f['foreignCategory']->id, ['name' => 'Hijack']],
        ['delete', '/categories/'.$f['foreignCategory']->id],
        ['get', '/products/'.$f['foreignProduct']->id],
        ['patch', '/products/'.$f['foreignProduct']->id, ['name' => 'Hijack']],
        ['delete', '/products/'.$f['foreignProduct']->id],
        ['post', '/products/'.$f['foreignProduct']->id.'/activate'],
        ['get', '/products/'.$f['foreignProduct']->id.'/variants'],
        ['delete', '/products/'.$f['foreignProduct']->id.'/variants/'.$f['foreignVariant']->id],
        ['get', '/products/'.$f['foreignProduct']->id.'/media'],
        ['delete', '/products/'.$f['foreignProduct']->id.'/media/'.$f['foreignMedia']->id],
        ['get', '/products/'.$f['foreignProduct']->id.'/outlets'],
        ['delete', '/products/'.$f['foreignProduct']->id.'/outlets/'.$f['foreignOutlet']->id],
        ['get', '/products/'.$f['foreignProduct']->id.'/modifier-groups'],
        ['get', '/products/'.$f['foreignProduct']->id.'/modifier-groups/'.$f['foreignGroup']->id],
        ['delete', '/products/'.$f['foreignProduct']->id.'/modifier-groups/'.$f['foreignGroup']->id],
        ['get', '/products/'.$f['foreignProduct']->id.'/modifier-groups/'.$f['foreignGroup']->id.'/modifiers'],
        ['delete', '/products/'.$f['foreignProduct']->id.'/modifier-groups/'.$f['foreignGroup']->id.'/modifiers/'.$f['foreignModifier']->id],
        ['get', '/products/'.$f['product']->id.'/modifier-groups/'.$f['foreignGroup']->id],
        ['get', '/products/'.$f['product']->id.'/modifier-groups/'.$f['group']->id.'/modifiers/'.$f['foreignModifier']->id],
        ['get', '/outlets/'.$f['foreignOutlet']->id.'/products'],
    ];

    foreach ($notFound as $request) {
        [$method, $path] = $request;

        $this->json($method, CATALOG_PREFIX.$path, $request[2] ?? [])
            ->assertStatus(404, "{$method} {$path}");
    }

    expect($f['foreignCategory']->fresh()->name)->not->toBe('Hijack')
        ->and($f['foreignProduct']->fresh()->name)->not->toBe('Hijack');
});

it('restricts outlet-scoped actions to the assigned outlet and role', function () {
    $f = authorizationFixture();
    $productId = $f['product']->id;
    $ownOutlet = $f['outlet']->id;
    $siblingOutlet = $f['otherOutlet']->id;

    $manager = authorizationEmployee($f['merchant'], OutletUserRole::OutletManager, $f['outlet']);
    Sanctum::actingAs($manager['user']);

    $this->getJson(CATALOG_PREFIX.'/outlets/'.$ownOutlet.'/products')->assertOk();
    $this->getJson(CATALOG_PREFIX.'/outlets/'.$siblingOutlet.'/products')->assertStatus(403);
    $this->putJson(CATALOG_PREFIX.'/outlets/'.$ownOutlet.'/products/order', [
        'items' => [['product_id' => $productId, 'display_order' => 1]],
    ])->assertStatus(204);
    $this->putJson(CATALOG_PREFIX.'/outlets/'.$siblingOutlet.'/products/order', [
        'items' => [['product_id' => $productId, 'display_order' => 1]],
    ])->assertStatus(403);
    $this->postJson(CATALOG_PREFIX.'/products/'.$productId.'/outlets/'.$ownOutlet.'/deactivate')->assertOk();
    $this->postJson(CATALOG_PREFIX.'/products/'.$productId.'/outlets/'.$ownOutlet.'/availability', ['status' => 'unavailable'])->assertOk();
    $this->postJson(CATALOG_PREFIX.'/products/'.$productId.'/outlets/'.$siblingOutlet.'/deactivate')->assertStatus(403);

    $staff = authorizationEmployee($f['merchant'], OutletUserRole::OutletStaff, $f['outlet']);
    Sanctum::actingAs($staff['user']);

    $this->getJson(CATALOG_PREFIX.'/outlets/'.$ownOutlet.'/products')->assertOk();
    $this->postJson(CATALOG_PREFIX.'/products/'.$productId.'/outlets/'.$ownOutlet.'/availability', ['status' => 'available'])->assertOk();
    $this->postJson(CATALOG_PREFIX.'/products/'.$productId.'/outlets/'.$ownOutlet.'/deactivate')
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_capability_forbidden');
    $this->putJson(CATALOG_PREFIX.'/outlets/'.$ownOutlet.'/products/order', [
        'items' => [['product_id' => $productId, 'display_order' => 1]],
    ])->assertStatus(403);
    $this->getJson(CATALOG_PREFIX.'/outlets/'.$siblingOutlet.'/products')->assertStatus(403);
});
