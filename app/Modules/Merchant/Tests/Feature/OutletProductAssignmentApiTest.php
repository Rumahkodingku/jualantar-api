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
use App\Modules\Merchant\Domain\Models\Product;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * @return array{owner: User, merchant: Merchant, product: Product, outletA: MerchantOutlet, outletB: MerchantOutlet}
 */
function catalogAssignmentOwner(): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    $outletA = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $outletB = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    return compact('owner', 'merchant', 'product', 'outletA', 'outletB');
}

/**
 * @return array{user: User, outlet: MerchantOutlet}
 */
function catalogEmployee(Merchant $merchant, OutletUserRole $role, ?MerchantOutlet $outlet = null): array
{
    $outlet ??= MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $user = test()->plainUser();

    MerchantOutletUser::factory()
        ->forOutlet($outlet)
        ->forUser($user->id)
        ->create(['role' => $role]);

    return compact('user', 'outlet');
}

function outletsUrl(Product $product, string $suffix = ''): string
{
    return '/api/v1/merchant/catalog/products/'.$product->id.'/outlets'.$suffix;
}

it('requires authentication for outlet assignments', function () {
    ['product' => $product] = catalogAssignmentOwner();

    $this->getJson(outletsUrl($product))->assertStatus(401);
});

it('forbids an outlet employee from assigning products', function () {
    ['merchant' => $merchant, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    ['user' => $manager] = catalogEmployee($merchant, OutletUserRole::OutletManager, $outlet);
    Sanctum::actingAs($manager);

    $this->getJson(outletsUrl($product))->assertStatus(403)->assertJsonPath('code', 'forbidden');
    $this->postJson(outletsUrl($product), ['outlet_ids' => [$outlet->id]])->assertStatus(403);
    $this->putJson(outletsUrl($product), ['outlet_ids' => []])->assertStatus(403);
});

it('assigns a product to multiple outlets', function () {
    ['owner' => $owner, 'merchant' => $merchant, 'product' => $product, 'outletA' => $outletA, 'outletB' => $outletB] = catalogAssignmentOwner();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product), ['outlet_ids' => [$outletA->id, $outletB->id]])
        ->assertStatus(201)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.availability_status', 'available')
        ->assertJsonPath('data.0.display_order', 1);

    expect(OutletProduct::query()->count())->toBe(2)
        ->and(OutletProduct::query()->where('merchant_id', $merchant->id)->count())->toBe(2);
});

it('assigns an inactive product without requiring activation', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    Sanctum::actingAs($owner);

    expect($product->status)->toBe(CatalogStatus::Inactive);

    $this->postJson(outletsUrl($product), ['outlet_ids' => [$outlet->id]])->assertStatus(201);
});

it('rejects a duplicate assignment all or nothing', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outletA, 'outletB' => $outletB] = catalogAssignmentOwner();
    OutletProduct::factory()->forOutlet($outletA)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product), ['outlet_ids' => [$outletA->id, $outletB->id]])
        ->assertStatus(409)
        ->assertJsonPath('code', 'duplicate_outlet_assignment');

    expect(OutletProduct::query()->count())->toBe(1)
        ->and(OutletProduct::query()->where('outlet_id', $outletB->id)->exists())->toBeFalse();
});

it('validates the outlet id list', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product), ['outlet_ids' => []])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['outlet_ids']]);

    $this->postJson(outletsUrl($product), ['outlet_ids' => [$outlet->id, $outlet->id]])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['outlet_ids.0', 'outlet_ids.1']]);
});

it('rejects assigning an outlet of another merchant', function () {
    ['owner' => $owner, 'product' => $product] = catalogAssignmentOwner();
    $foreignOutlet = MerchantOutlet::factory()->create();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product), ['outlet_ids' => [$foreignOutlet->id]])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('replaces assignments keeping existing ones untouched', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outletA, 'outletB' => $outletB] = catalogAssignmentOwner();
    $outletC = MerchantOutlet::factory()->create(['merchant_id' => $product->merchant_id]);

    $kept = OutletProduct::factory()->forOutlet($outletB)->forProduct($product)->create([
        'status' => CatalogStatus::Inactive,
        'availability_status' => ProductAvailabilityStatus::Unavailable,
        'unavailable_reason' => 'Stok habis',
        'display_order' => 7,
    ]);
    $dropped = OutletProduct::factory()->forOutlet($outletA)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->putJson(outletsUrl($product), ['outlet_ids' => [$outletB->id, $outletC->id]])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(OutletProduct::query()->find($dropped->id))->toBeNull()
        ->and(OutletProduct::withTrashed()->find($dropped->id))->not->toBeNull();

    $kept->refresh();

    expect($kept->status)->toBe(CatalogStatus::Inactive)
        ->and($kept->availability_status)->toBe(ProductAvailabilityStatus::Unavailable)
        ->and($kept->unavailable_reason)->toBe('Stok habis')
        ->and($kept->display_order)->toBe(7)
        ->and(OutletProduct::query()->where('outlet_id', $outletC->id)->exists())->toBeTrue();
});

it('detaches the product from every outlet with an empty replace', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outletA] = catalogAssignmentOwner();
    OutletProduct::factory()->forOutlet($outletA)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->putJson(outletsUrl($product), ['outlet_ids' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect(OutletProduct::query()->count())->toBe(0);
});

it('removes a single assignment and supports assigning again', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    $original = OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->deleteJson(outletsUrl($product, '/'.$outlet->id))->assertStatus(204);

    expect(OutletProduct::query()->find($original->id))->toBeNull()
        ->and(Product::query()->find($product->id))->not->toBeNull();

    $this->postJson(outletsUrl($product), ['outlet_ids' => [$outlet->id]])->assertStatus(201);

    $recreated = OutletProduct::query()->where('outlet_id', $outlet->id)->first();

    expect($recreated->id)->not->toBe($original->id)
        ->and(OutletProduct::withTrashed()->find($original->id))->not->toBeNull();
});

it('returns 404 when removing an outlet that is not assigned', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    Sanctum::actingAs($owner);

    $this->deleteJson(outletsUrl($product, '/'.$outlet->id))
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('lets the owner activate and deactivate an assignment', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product, '/'.$outlet->id.'/deactivate'))
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    $this->postJson(outletsUrl($product, '/'.$outlet->id.'/activate'))
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('lets a manager change assignment status on their own outlet only', function () {
    ['merchant' => $merchant, 'product' => $product, 'outletA' => $outletA, 'outletB' => $outletB] = catalogAssignmentOwner();
    OutletProduct::factory()->forOutlet($outletA)->forProduct($product)->create();
    OutletProduct::factory()->forOutlet($outletB)->forProduct($product)->create();
    ['user' => $manager] = catalogEmployee($merchant, OutletUserRole::OutletManager, $outletA);
    Sanctum::actingAs($manager);

    $this->postJson(outletsUrl($product, '/'.$outletA->id.'/deactivate'))
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    $this->postJson(outletsUrl($product, '/'.$outletB->id.'/deactivate'))
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_scope_forbidden');
});

it('forbids staff from changing assignment status', function () {
    ['merchant' => $merchant, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    ['user' => $staff] = catalogEmployee($merchant, OutletUserRole::OutletStaff, $outlet);
    Sanctum::actingAs($staff);

    $this->postJson(outletsUrl($product, '/'.$outlet->id.'/deactivate'))
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_capability_forbidden');
});

it('returns 404 for an outlet of another merchant', function () {
    ['owner' => $owner, 'product' => $product] = catalogAssignmentOwner();
    $foreignOutlet = MerchantOutlet::factory()->create();
    $foreignProduct = Product::factory()->simple()->create(['merchant_id' => $foreignOutlet->merchant_id]);
    OutletProduct::factory()->forOutlet($foreignOutlet)->forProduct($foreignProduct)->create();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($foreignProduct, '/'.$foreignOutlet->id.'/deactivate'))
        ->assertStatus(404)
        ->assertJsonPath('code', 'outlet_not_found');
});

it('lets staff update availability on their own outlet', function () {
    ['merchant' => $merchant, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    $assignment = OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    ['user' => $staff] = catalogEmployee($merchant, OutletUserRole::OutletStaff, $outlet);
    Sanctum::actingAs($staff);

    $this->postJson(outletsUrl($product, '/'.$outlet->id.'/availability'), [
        'status' => 'unavailable',
        'reason' => 'Stok ayam habis',
    ])
        ->assertOk()
        ->assertJsonPath('data.availability_status', 'unavailable')
        ->assertJsonPath('data.unavailable_reason', 'Stok ayam habis');

    expect($assignment->fresh()->availability_status)->toBe(ProductAvailabilityStatus::Unavailable);
});

it('clears the reason when availability returns to available', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->unavailable('Stok habis')->create();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product, '/'.$outlet->id.'/availability'), ['status' => 'available'])
        ->assertOk()
        ->assertJsonPath('data.availability_status', 'available')
        ->assertJsonPath('data.unavailable_reason', null);
});

it('rejects a reason when marking available', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product, '/'.$outlet->id.'/availability'), [
        'status' => 'available',
        'reason' => 'Tidak relevan',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['reason']]);
});

it('keeps availability isolated between outlets', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outletA, 'outletB' => $outletB] = catalogAssignmentOwner();
    $first = OutletProduct::factory()->forOutlet($outletA)->forProduct($product)->create();
    $second = OutletProduct::factory()->forOutlet($outletB)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product, '/'.$outletA->id.'/availability'), ['status' => 'unavailable'])->assertOk();

    expect($first->fresh()->availability_status)->toBe(ProductAvailabilityStatus::Unavailable)
        ->and($second->fresh()->availability_status)->toBe(ProductAvailabilityStatus::Available);
});

it('lists assignments with filters', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outletA, 'outletB' => $outletB] = catalogAssignmentOwner();
    OutletProduct::factory()->forOutlet($outletA)->forProduct($product)->inactive()->create(['display_order' => 1]);
    OutletProduct::factory()->forOutlet($outletB)->forProduct($product)->unavailable()->create(['display_order' => 2]);
    Sanctum::actingAs($owner);

    $this->getJson(outletsUrl($product))
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.outlet.id', $outletA->id);

    $this->getJson(outletsUrl($product).'?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.outlet_id', $outletA->id);

    $this->getJson(outletsUrl($product).'?availability=unavailable')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.outlet_id', $outletB->id);
});

it('returns 404 when the product is not assigned to the outlet', function () {
    ['owner' => $owner, 'product' => $product, 'outletA' => $outlet] = catalogAssignmentOwner();
    Sanctum::actingAs($owner);

    $this->postJson(outletsUrl($product, '/'.$outlet->id.'/availability'), ['status' => 'unavailable'])
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});
