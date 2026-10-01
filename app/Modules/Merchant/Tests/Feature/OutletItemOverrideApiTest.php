<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\OutletUserRole;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Modules\Merchant\Domain\Models\OutletProductModifier;
use App\Modules\Merchant\Domain\Models\OutletProductModifierGroup;
use App\Modules\Merchant\Domain\Models\OutletProductVariant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * @return array{owner: User, merchant: Merchant, outlet: MerchantOutlet, category: CatalogCategory, manager: User}
 */
function overrideFixture(): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $manager = test()->plainUser();
    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($manager->id)->create([
        'role' => OutletUserRole::OutletManager,
    ]);

    return compact('owner', 'merchant', 'outlet', 'category', 'manager');
}

function overrideProduct(MerchantOutlet $outlet, CatalogCategory $category, array $attributes = []): Product
{
    $product = Product::factory()->variable()->active()->create([
        'merchant_id' => $outlet->merchant_id,
        'category_id' => $category->id,
        ...$attributes,
    ]);

    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create([
        'status' => CatalogStatus::Active,
        'availability_status' => ProductAvailabilityStatus::Available,
    ]);

    return $product;
}

function variantUrl(MerchantOutlet $outlet, Product $product, string $variant): string
{
    return "/api/v1/merchant/catalog/outlets/{$outlet->id}/products/{$product->id}/variants/{$variant}";
}

function groupUrl(MerchantOutlet $outlet, Product $product, string $group, string $suffix = ''): string
{
    return "/api/v1/merchant/catalog/outlets/{$outlet->id}/products/{$product->id}/modifier-groups/{$group}{$suffix}";
}

function masterVariantUrl(Product $product, string $variant): string
{
    return "/api/v1/merchant/catalog/products/{$product->id}/variants/{$variant}";
}

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

it('requires authentication to change an outlet item status', function () {
    ['outlet' => $outlet, 'category' => $category] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertStatus(401);
});

it('never lets outlet staff change a variant or customization status', function () {
    ['outlet' => $outlet, 'category' => $category] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $staff = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($staff->id)->create([
        'role' => OutletUserRole::OutletStaff,
    ]);
    Sanctum::actingAs($staff);

    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertForbidden();
    $this->assertDatabaseMissing('merchant.outlet_product_variants', ['product_variant_id' => $variant->id]);
});

it('forbids a manager of another outlet and hides a foreign outlet', function () {
    ['merchant' => $merchant, 'outlet' => $outlet, 'category' => $category] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $sibling = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $outsider = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($sibling)->forUser($outsider->id)->create([
        'role' => OutletUserRole::OutletManager,
    ]);
    Sanctum::actingAs($outsider);

    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertForbidden();

    $foreignOutlet = MerchantOutlet::factory()->create();
    $this->postJson(variantUrl($foreignOutlet, $product, $variant->id).'/deactivate')->assertNotFound();
});

it('hides a product that is not assigned to the outlet', function () {
    ['merchant' => $merchant, 'outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $variant = ProductVariant::factory()->forProduct($product)->create();

    $otherOutlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    MerchantOutletUser::factory()->forOutlet($otherOutlet)->forUser($manager->id)->create([
        'role' => OutletUserRole::OutletManager,
    ]);
    Sanctum::actingAs($manager);

    $this->postJson(variantUrl($otherOutlet, $product, $variant->id).'/deactivate')->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Variant overrides
|--------------------------------------------------------------------------
*/

it('lets a manager hide and restore one variant at their outlet only', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Keep']);
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $siblingOutlet = MerchantOutlet::factory()->create(['merchant_id' => $outlet->merchant_id]);
    OutletProduct::factory()->forOutlet($siblingOutlet)->forProduct($product)->create();

    Sanctum::actingAs($manager);

    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.subject_type', 'variant')
        ->assertJsonPath('data.item_id', $variant->id)
        ->assertJsonPath('data.outlet_id', $outlet->id)
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.deactivated_by.id', $manager->id);

    // The master status is the ceiling and stays untouched.
    expect($variant->refresh()->status)->toBe(CatalogStatus::Active);

    $this->postJson(variantUrl($outlet, $product, $variant->id).'/reset')->assertNoContent();

    $this->assertSoftDeleted('merchant.outlet_product_variants', [
        'outlet_id' => $outlet->id,
        'product_variant_id' => $variant->id,
    ]);
    expect($variant->refresh()->status)->toBe(CatalogStatus::Active);
});

it('keeps an override per outlet instead of leaking it to the others', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Keep']);
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $otherOutlet = MerchantOutlet::factory()->create(['merchant_id' => $outlet->merchant_id]);
    OutletProduct::factory()->forOutlet($otherOutlet)->forProduct($product)->create();
    MerchantOutletUser::factory()->forOutlet($otherOutlet)->forUser($manager->id)->create([
        'role' => OutletUserRole::OutletManager,
    ]);

    Sanctum::actingAs($manager);
    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertOk();

    expect(OutletProductVariant::query()->count())->toBe(1);
});

it('refuses to hide the last variant that keeps the product sellable at the outlet', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $only = ProductVariant::factory()->forProduct($product)->create();

    Sanctum::actingAs($manager);

    $this->postJson(variantUrl($outlet, $product, $only->id).'/deactivate')
        ->assertStatus(409)
        ->assertJsonPath('code', 'outlet_last_sellable_variant');

    $this->assertDatabaseMissing('merchant.outlet_product_variants', ['product_variant_id' => $only->id]);
});

it('allows hiding a variant once another one keeps the product sellable', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Keep']);
    $variant = ProductVariant::factory()->forProduct($product)->create();

    Sanctum::actingAs($manager);

    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertOk();
});

it('stops guarding the variant count once the outlet already turned the product off', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $only = ProductVariant::factory()->forProduct($product)->create();

    OutletProduct::query()->where('product_id', $product->id)->update(['status' => CatalogStatus::Inactive]);

    Sanctum::actingAs($manager);

    $this->postJson(variantUrl($outlet, $product, $only->id).'/deactivate')->assertOk();
});

it('refuses to override a variant the owner already deactivated', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $variant = ProductVariant::factory()->forProduct($product)->inactive()->create();

    Sanctum::actingAs($manager);

    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')
        ->assertStatus(409)
        ->assertJsonPath('code', 'outlet_item_master_inactive');
});

it('hides a variant belonging to another product of the same merchant', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $other = overrideProduct($outlet, $category);
    $foreignVariant = ProductVariant::factory()->forProduct($other)->create();

    Sanctum::actingAs($manager);

    $this->postJson(variantUrl($outlet, $product, $foreignVariant->id).'/deactivate')->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Customization overrides
|--------------------------------------------------------------------------
*/

it('lets a manager hide and restore a customization group', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $group = ProductModifierGroup::factory()->forProduct($product)->active()->required()->create();
    ProductModifier::factory()->forGroup($group)->create();

    Sanctum::actingAs($manager);

    $this->postJson(groupUrl($outlet, $product, $group->id).'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.subject_type', 'modifier_group')
        ->assertJsonPath('data.status', 'inactive');

    $this->postJson(groupUrl($outlet, $product, $group->id).'/reset')->assertNoContent();

    expect($group->refresh()->status)->toBe(CatalogStatus::Active);
});

it('refuses to hide an option a required group still needs at the outlet', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $group = ProductModifierGroup::factory()->forProduct($product)->active()->required()->create();
    $only = ProductModifier::factory()->forGroup($group)->create();

    Sanctum::actingAs($manager);

    $this->postJson(groupUrl($outlet, $product, $group->id, "/modifiers/{$only->id}/deactivate"))
        ->assertStatus(409)
        ->assertJsonPath('code', 'outlet_modifier_required_by_active_group');
});

it('allows hiding options while the whole group is hidden at that outlet', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $group = ProductModifierGroup::factory()->forProduct($product)->active()->required()->create();
    $only = ProductModifier::factory()->forGroup($group)->create();

    Sanctum::actingAs($manager);

    $this->postJson(groupUrl($outlet, $product, $group->id).'/deactivate')->assertOk();
    $this->postJson(groupUrl($outlet, $product, $group->id, "/modifiers/{$only->id}/deactivate"))->assertOk();
});

it('refuses to bring a group back when too few of its options are active here', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $group = ProductModifierGroup::factory()->forProduct($product)->active()->required()->create();
    $keep = ProductModifier::factory()->forGroup($group)->create(['name' => 'Keep']);
    $extra = ProductModifier::factory()->forGroup($group)->create(['name' => 'Extra']);

    Sanctum::actingAs($manager);

    $this->postJson(groupUrl($outlet, $product, $group->id).'/deactivate')->assertOk();
    $this->postJson(groupUrl($outlet, $product, $group->id, "/modifiers/{$extra->id}/deactivate"))->assertOk();
    $this->postJson(groupUrl($outlet, $product, $group->id, "/modifiers/{$keep->id}/deactivate"))->assertOk();

    $this->postJson(groupUrl($outlet, $product, $group->id).'/reset')
        ->assertStatus(409)
        ->assertJsonPath('code', 'outlet_modifier_group_unsatisfiable');

    // The failed reset left the group hidden, so the outlet state is unchanged.
    expect(OutletProductModifierGroup::query()->count())->toBe(1);
});

it('hides an option belonging to another group as a 404', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $group = ProductModifierGroup::factory()->forProduct($product)->active()->create();
    $otherGroup = ProductModifierGroup::factory()->forProduct($product)->active()->create();
    $foreign = ProductModifier::factory()->forGroup($otherGroup)->create();

    Sanctum::actingAs($manager);

    $this->postJson(groupUrl($outlet, $product, $group->id, "/modifiers/{$foreign->id}/deactivate"))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Projections
|--------------------------------------------------------------------------
*/

it('reports the effective status of every item on the outlet product detail', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $keep = ProductVariant::factory()->forProduct($product)->create(['name' => 'Keep', 'display_order' => 1]);
    $hidden = ProductVariant::factory()->forProduct($product)->create(['name' => 'Hidden', 'display_order' => 2]);
    $masterOff = ProductVariant::factory()->forProduct($product)->inactive()->create(['name' => 'MasterOff', 'display_order' => 3]);

    $group = ProductModifierGroup::factory()->forProduct($product)->active()->required()->create(['display_order' => 1]);
    $optionKept = ProductModifier::factory()->forGroup($group)->create(['name' => 'OptionKept', 'display_order' => 1]);
    $optionHidden = ProductModifier::factory()->forGroup($group)->create(['name' => 'OptionHidden', 'display_order' => 2]);

    $inactiveGroup = ProductModifierGroup::factory()->forProduct($product)->inactive()->create(['display_order' => 2]);

    Sanctum::actingAs($manager);
    $this->postJson(variantUrl($outlet, $product, $hidden->id).'/deactivate')->assertOk();
    $this->postJson(groupUrl($outlet, $product, $group->id, "/modifiers/{$optionHidden->id}/deactivate"))->assertOk();

    $url = "/api/v1/merchant/catalog/outlets/{$outlet->id}/products/{$product->id}";
    $this->getJson($url)
        ->assertOk()
        // The hidden and master-inactive items are visible here so they can be managed.
        ->assertJsonPath('data.variants.0.id', $keep->id)
        ->assertJsonPath('data.variants.0.effective_status', 'active')
        ->assertJsonPath('data.variants.0.is_overridden', false)
        ->assertJsonPath('data.variants.1.id', $hidden->id)
        ->assertJsonPath('data.variants.1.status', 'active')
        ->assertJsonPath('data.variants.1.effective_status', 'inactive')
        ->assertJsonPath('data.variants.1.is_overridden', true)
        ->assertJsonPath('data.variants.2.id', $masterOff->id)
        ->assertJsonPath('data.variants.2.status', 'inactive')
        ->assertJsonPath('data.variants.2.effective_status', 'inactive')
        ->assertJsonPath('data.variants.2.is_overridden', false)
        ->assertJsonPath('data.modifier_groups.0.id', $group->id)
        ->assertJsonPath('data.modifier_groups.0.modifiers.0.id', $optionKept->id)
        ->assertJsonPath('data.modifier_groups.0.modifiers.0.effective_status', 'active')
        ->assertJsonPath('data.modifier_groups.0.modifiers.1.id', $optionHidden->id)
        ->assertJsonPath('data.modifier_groups.0.modifiers.1.effective_status', 'inactive')
        ->assertJsonPath('data.modifier_groups.1.id', $inactiveGroup->id)
        ->assertJsonPath('data.is_sellable', true);
});

it('hides overridden items from the outlet catalog list and marks the product unsellable', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $only = ProductVariant::factory()->forProduct($product)->create();
    $second = ProductVariant::factory()->forProduct($product)->create();

    Sanctum::actingAs($manager);
    $this->postJson(variantUrl($outlet, $product, $only->id).'/deactivate')->assertOk();

    $url = "/api/v1/merchant/catalog/outlets/{$outlet->id}/products";
    $this->getJson($url)
        ->assertOk()
        ->assertJsonCount(1, 'data.0.variants')
        ->assertJsonPath('data.0.variants.0.id', $second->id)
        ->assertJsonPath('data.0.is_sellable', true);
});

it('marks the product unsellable when the only remaining variant is hidden', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $keep = ProductVariant::factory()->forProduct($product)->create();
    $only = ProductVariant::factory()->forProduct($product)->create();

    Sanctum::actingAs($manager);
    $this->postJson(variantUrl($outlet, $product, $keep->id).'/deactivate')->assertOk();

    // Force the state the guard would otherwise refuse, to prove is_sellable
    // reads the effective count and not the master count.
    OutletProductVariant::query()->create([
        'merchant_id' => $outlet->merchant_id,
        'outlet_id' => $outlet->id,
        'product_id' => $product->id,
        'product_variant_id' => $only->id,
        'status' => CatalogStatus::Inactive,
    ]);

    $url = "/api/v1/merchant/catalog/outlets/{$outlet->id}/products/{$product->id}";
    $this->getJson($url)->assertOk()->assertJsonPath('data.is_sellable', false);
});

/*
|--------------------------------------------------------------------------
| Owner reverse view and reset
|--------------------------------------------------------------------------
*/

it('shows the owner which outlets hid each item and who did it', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Keep', 'display_order' => 1]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['name' => 'Hidden', 'display_order' => 2]);

    Sanctum::actingAs($manager);
    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertOk();

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/merchant/catalog/products/{$product->id}")
        ->assertOk()
        // The untouched variant reports no overrides at all.
        ->assertJsonCount(0, 'data.variants.0.outlet_overrides')
        ->assertJsonCount(1, 'data.variants.1.outlet_overrides')
        ->assertJsonPath('data.variants.1.outlet_overrides.0.subject_type', 'variant')
        ->assertJsonPath('data.variants.1.outlet_overrides.0.item_id', $variant->id)
        ->assertJsonPath('data.variants.1.outlet_overrides.0.status', 'inactive')
        ->assertJsonPath('data.variants.1.outlet_overrides.0.deactivated_by.id', $manager->id)
        ->assertJsonPath('data.variants.1.outlet_overrides.0.deactivated_by.email', $manager->email);
});

it('lets the owner clear the overrides of one item at every outlet', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Keep']);
    $variant = ProductVariant::factory()->forProduct($product)->create();
    $siblingOutlet = MerchantOutlet::factory()->create(['merchant_id' => $outlet->merchant_id]);
    OutletProduct::factory()->forOutlet($siblingOutlet)->forProduct($product)->create();
    OutletProductVariant::query()->create([
        'merchant_id' => $outlet->merchant_id,
        'outlet_id' => $siblingOutlet->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'status' => CatalogStatus::Inactive,
    ]);

    Sanctum::actingAs($manager);
    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertOk();
    expect(OutletProductVariant::query()->count())->toBe(2);

    Sanctum::actingAs($owner);
    $this->deleteJson(masterVariantUrl($product, $variant->id).'/outlet-overrides')->assertNoContent();

    expect(OutletProductVariant::query()->count())->toBe(0);
});

it('keeps the owner reset endpoints owner-only', function () {
    ['outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $variant = ProductVariant::factory()->forProduct($product)->create();

    Sanctum::actingAs($manager);
    $this->deleteJson(masterVariantUrl($product, $variant->id).'/outlet-overrides')->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Master delete cascade
|--------------------------------------------------------------------------
*/

it('drops the overrides together with the deleted master items', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    $keep = ProductVariant::factory()->forProduct($product)->create(['display_order' => 1]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['display_order' => 2]);
    $group = ProductModifierGroup::factory()->forProduct($product)->active()->multiple()->create();
    ProductModifier::factory()->forGroup($group)->create(['name' => 'Keep']);
    $option = ProductModifier::factory()->forGroup($group)->create();

    Sanctum::actingAs($manager);
    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertOk();
    $this->postJson(groupUrl($outlet, $product, $group->id, "/modifiers/{$option->id}/deactivate"))->assertOk();

    Sanctum::actingAs($owner);
    $this->deleteJson("/api/v1/merchant/catalog/products/{$product->id}")->assertNoContent();

    expect(OutletProductVariant::withTrashed()->where('product_variant_id', $variant->id)->count())->toBe(1)
        ->and(OutletProductModifier::withTrashed()->where('product_modifier_id', $option->id)->count())->toBe(1)
        ->and($keep->id)->not->toBe($variant->id);
});

it('clears a deleted variant overrides when only the variant is removed', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category, 'manager' => $manager] = overrideFixture();
    $product = overrideProduct($outlet, $category);
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Keep']);
    $variant = ProductVariant::factory()->forProduct($product)->create();

    Sanctum::actingAs($manager);
    $this->postJson(variantUrl($outlet, $product, $variant->id).'/deactivate')->assertOk();

    Sanctum::actingAs($owner);
    $this->deleteJson(masterVariantUrl($product, $variant->id))->assertNoContent();

    expect(OutletProductVariant::withTrashed()->where('product_variant_id', $variant->id)->first()?->deleted_at)
        ->not->toBeNull();
});
