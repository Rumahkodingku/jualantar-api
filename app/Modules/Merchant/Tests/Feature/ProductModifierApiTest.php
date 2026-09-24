<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * @return array{owner: User, merchant: Merchant, product: Product}
 */
function modifierFixture(): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->simple()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $category->id,
    ]);

    Sanctum::actingAs($owner);

    return compact('owner', 'merchant', 'product');
}

function modifiersUrl(Product $product, ProductModifierGroup $group, string $suffix = ''): string
{
    return '/api/v1/merchant/catalog/products/'.$product->id
        .'/modifier-groups/'.$group->id.'/modifiers'.$suffix;
}

it('requires authentication for modifiers', function () {
    $group = ProductModifierGroup::factory()->create();

    $this->getJson('/api/v1/merchant/catalog/products/'.$group->product_id.'/modifier-groups/'.$group->id.'/modifiers')
        ->assertStatus(401);
});

it('creates an active modifier', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();

    $this->postJson(modifiersUrl($product, $group), [
        'name' => 'Extra Cheese',
        'description' => 'Tambahan keju',
        'price' => 5000,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Extra Cheese')
        ->assertJsonPath('data.price', '5000.00')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_default', false)
        ->assertJsonPath('data.display_order', 1);
});

it('rejects a negative price', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();

    $this->postJson(modifiersUrl($product, $group), ['name' => 'Diskon', 'price' => -1])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['price']]);
});

it('rejects a status in the payload', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();

    $this->postJson(modifiersUrl($product, $group), ['name' => 'Extra', 'price' => 0, 'status' => 'active'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['status']]);
});

it('rejects a duplicate modifier name case-insensitively', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    ProductModifier::factory()->forGroup($group)->create(['name' => 'Extra Cheese']);

    $this->postJson(modifiersUrl($product, $group), ['name' => 'EXTRA CHEESE', 'price' => 1000])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('lists and filters modifiers without pagination', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    ProductModifier::factory()->forGroup($group)->create(['name' => 'Kedua', 'display_order' => 2]);
    ProductModifier::factory()->forGroup($group)->inactive()->create(['name' => 'Pertama', 'display_order' => 1]);

    $response = $this->getJson(modifiersUrl($product, $group))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Pertama');

    expect($response->json())->not->toHaveKey('meta');

    $this->getJson(modifiersUrl($product, $group).'?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Kedua');
});

it('updates a modifier name and price', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    $modifier = ProductModifier::factory()->forGroup($group)->create(['name' => 'Extra', 'price' => 1000]);

    $this->patchJson(modifiersUrl($product, $group, '/'.$modifier->id), ['name' => 'Extra Besar', 'price' => 7500])
        ->assertOk()
        ->assertJsonPath('data.name', 'Extra Besar')
        ->assertJsonPath('data.price', '7500.00');
});

it('refuses to move a modifier to another group', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    $other = ProductModifierGroup::factory()->forProduct($product)->create();
    $modifier = ProductModifier::factory()->forGroup($group)->create();

    $this->patchJson(modifiersUrl($product, $group, '/'.$modifier->id), ['modifier_group_id' => $other->id])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['modifier_group_id']]);
});

it('keeps a single default by swapping the previous one', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->single()->create();

    $first = $this->postJson(modifiersUrl($product, $group), ['name' => 'Normal', 'price' => 0, 'is_default' => true])
        ->assertStatus(201)
        ->assertJsonPath('data.is_default', true)
        ->json('data.id');

    $this->postJson(modifiersUrl($product, $group), ['name' => 'Pedas', 'price' => 0, 'is_default' => true])
        ->assertStatus(201)
        ->assertJsonPath('data.is_default', true);

    expect(ProductModifier::query()->find($first)->is_default)->toBeFalse();
});

it('limits the defaults of a multiple group to the max selection', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create([
        'selection_type' => 'multiple',
        'min_selection' => 0,
        'max_selection' => 2,
        'is_required' => false,
    ]);

    ProductModifier::factory()->forGroup($group)->default()->create();
    ProductModifier::factory()->forGroup($group)->default()->create();

    $this->postJson(modifiersUrl($product, $group), ['name' => 'Ketiga', 'price' => 0, 'is_default' => true])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['is_default']]);
});

it('refuses a default on an inactive modifier', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    $modifier = ProductModifier::factory()->forGroup($group)->inactive()->create();

    $this->patchJson(modifiersUrl($product, $group, '/'.$modifier->id), ['is_default' => true])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['is_default']]);
});

it('resets the default when the modifier is deactivated or deleted', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->single()->create();
    $modifier = ProductModifier::factory()->forGroup($group)->default()->create();

    $this->postJson(modifiersUrl($product, $group, '/'.$modifier->id.'/deactivate'))
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.is_default', false);

    $other = ProductModifier::factory()->forGroup($group)->default()->create();
    $this->deleteJson(modifiersUrl($product, $group, '/'.$other->id))->assertStatus(204);

    expect(ProductModifier::withTrashed()->find($other->id)->is_default)->toBeFalse();
});

it('protects the invariant when deactivating a modifier on an active group', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create([
        'selection_type' => 'multiple',
        'min_selection' => 1,
        'max_selection' => 3,
        'is_required' => true,
        'status' => CatalogStatus::Active,
    ]);
    $modifier = ProductModifier::factory()->forGroup($group)->create();

    $this->postJson(modifiersUrl($product, $group, '/'.$modifier->id.'/deactivate'))
        ->assertStatus(409)
        ->assertJsonPath('code', 'modifier_required_by_active_group');

    expect($modifier->fresh()->status)->toBe(CatalogStatus::Active);

    ProductModifier::factory()->forGroup($group)->create();

    $this->postJson(modifiersUrl($product, $group, '/'.$modifier->id.'/deactivate'))->assertOk();
});

it('allows deactivating and deleting on an inactive group', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->inactive()->create([
        'selection_type' => 'multiple',
        'min_selection' => 1,
        'max_selection' => 3,
        'is_required' => true,
    ]);
    $first = ProductModifier::factory()->forGroup($group)->create();
    $second = ProductModifier::factory()->forGroup($group)->create();

    $this->postJson(modifiersUrl($product, $group, '/'.$first->id.'/deactivate'))->assertOk();
    $this->deleteJson(modifiersUrl($product, $group, '/'.$second->id))->assertStatus(204);
});

it('protects the invariant when deleting a modifier on an active group', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create([
        'selection_type' => 'multiple',
        'min_selection' => 1,
        'max_selection' => 3,
        'is_required' => true,
        'status' => CatalogStatus::Active,
    ]);
    $modifier = ProductModifier::factory()->forGroup($group)->create();

    $this->deleteJson(modifiersUrl($product, $group, '/'.$modifier->id))
        ->assertStatus(409)
        ->assertJsonPath('code', 'modifier_required_by_active_group');

    expect(ProductModifier::query()->find($modifier->id))->not->toBeNull();
});

it('reorders only the submitted modifiers and rejects ones outside the group', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    $first = ProductModifier::factory()->forGroup($group)->create(['display_order' => 1]);
    $second = ProductModifier::factory()->forGroup($group)->create(['display_order' => 2]);
    $foreign = ProductModifier::factory()->create();

    $this->putJson(modifiersUrl($product, $group, '/order'), [
        'items' => [
            ['modifier_id' => $first->id, 'display_order' => 5],
            ['modifier_id' => $second->id, 'display_order' => 4],
        ],
    ])->assertStatus(204);

    expect($first->fresh()->display_order)->toBe(5)
        ->and($second->fresh()->display_order)->toBe(4);

    $this->putJson(modifiersUrl($product, $group, '/order'), [
        'items' => [['modifier_id' => $foreign->id, 'display_order' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('enforces the modifier limit per group', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    ProductModifier::factory()->forGroup($group)->count(50)->create();

    $this->postJson(modifiersUrl($product, $group), ['name' => 'Kelebihan', 'price' => 0])
        ->assertStatus(409)
        ->assertJsonPath('code', 'modifier_limit_reached');
});

it('returns 404 for a modifier outside the group scope', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    $otherGroup = ProductModifierGroup::factory()->forProduct($product)->create();
    $modifierOfOtherGroup = ProductModifier::factory()->forGroup($otherGroup)->create();
    $foreign = ProductModifier::factory()->create();

    $this->getJson(modifiersUrl($product, $group, '/'.$modifierOfOtherGroup->id))->assertStatus(404);
    $this->getJson(modifiersUrl($product, $group, '/'.$foreign->id))->assertStatus(404);
});

it('locks the group row while guarding the invariant', function () {
    ['product' => $product] = modifierFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create([
        'selection_type' => 'multiple',
        'min_selection' => 1,
        'max_selection' => 3,
        'is_required' => true,
        'status' => CatalogStatus::Active,
    ]);
    $modifier = ProductModifier::factory()->forGroup($group)->create();

    DB::enableQueryLog();
    $this->postJson(modifiersUrl($product, $group, '/'.$modifier->id.'/deactivate'))->assertStatus(409);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $locked = $queries->contains(
        fn (string $query): bool => str_contains(strtolower($query), 'product_modifier_groups')
            && str_contains(strtolower($query), 'for update'),
    );

    expect($locked)->toBeTrue();
});
