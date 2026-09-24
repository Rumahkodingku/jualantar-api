<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * @return array{owner: User, merchant: Merchant, product: Product}
 */
function modifierGroupFixture(): array
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

function modifierGroupsUrl(Product $product, string $suffix = ''): string
{
    return '/api/v1/merchant/catalog/products/'.$product->id.'/modifier-groups'.$suffix;
}

it('requires authentication for modifier groups', function () {
    $product = Product::factory()->simple()->create();

    $this->getJson(modifierGroupsUrl($product))->assertStatus(401);
});

it('creates an inactive single modifier group with a default max of one', function () {
    ['product' => $product] = modifierGroupFixture();

    $this->postJson(modifierGroupsUrl($product), [
        'name' => 'Pilihan Saus',
        'description' => 'Pilih satu saus favorit',
        'selection_type' => 'single',
        'min_selection' => 1,
        'is_required' => true,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Pilihan Saus')
        ->assertJsonPath('data.selection_type', 'single')
        ->assertJsonPath('data.min_selection', 1)
        ->assertJsonPath('data.max_selection', 1)
        ->assertJsonPath('data.is_required', true)
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.display_order', 1);
});

it('normalizes is_required into the min selection', function () {
    ['product' => $product] = modifierGroupFixture();

    $this->postJson(modifierGroupsUrl($product), [
        'name' => 'Tambahan',
        'selection_type' => 'multiple',
        'is_required' => true,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.min_selection', 1)
        ->assertJsonPath('data.is_required', true);

    $this->postJson(modifierGroupsUrl($product), [
        'name' => 'Opsional',
        'selection_type' => 'multiple',
        'is_required' => false,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.min_selection', 0)
        ->assertJsonPath('data.is_required', false);
});

it('rejects an is_required flag that contradicts the min selection', function () {
    ['product' => $product] = modifierGroupFixture();

    $this->postJson(modifierGroupsUrl($product), [
        'name' => 'Kontradiktif',
        'selection_type' => 'multiple',
        'min_selection' => 0,
        'is_required' => true,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['is_required']]);
});

it('rejects invalid selection rules', function (array $payload, string $field) {
    ['product' => $product] = modifierGroupFixture();

    $this->postJson(modifierGroupsUrl($product), $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => [$field]]);
})->with([
    'single with max not one' => [['name' => 'A', 'selection_type' => 'single', 'max_selection' => 2], 'max_selection'],
    'max zero' => [['name' => 'B', 'selection_type' => 'multiple', 'max_selection' => 0], 'max_selection'],
    'max below min' => [['name' => 'C', 'selection_type' => 'multiple', 'min_selection' => 3, 'max_selection' => 2, 'is_required' => true], 'max_selection'],
]);

it('rejects a status in the payload', function () {
    ['product' => $product] = modifierGroupFixture();

    $this->postJson(modifierGroupsUrl($product), [
        'name' => 'Pilihan',
        'selection_type' => 'single',
        'status' => 'active',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['status']]);
});

it('rejects a duplicate group name case-insensitively and allows reuse after delete', function () {
    ['product' => $product] = modifierGroupFixture();
    ProductModifierGroup::factory()->forProduct($product)->create(['name' => 'Pilihan Saus']);

    $this->postJson(modifierGroupsUrl($product), [
        'name' => 'pilihan saus',
        'selection_type' => 'single',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');

    ProductModifierGroup::query()->where('name', 'Pilihan Saus')->firstOrFail()->delete();

    $this->postJson(modifierGroupsUrl($product), [
        'name' => 'Pilihan Saus',
        'selection_type' => 'single',
    ])->assertStatus(201);
});

it('lists and filters modifier groups without pagination', function () {
    ['product' => $product] = modifierGroupFixture();
    ProductModifierGroup::factory()->forProduct($product)->create(['name' => 'Kedua', 'display_order' => 2]);
    ProductModifierGroup::factory()->forProduct($product)->active()->create(['name' => 'Pertama', 'display_order' => 1]);

    $response = $this->getJson(modifierGroupsUrl($product))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Pertama');

    expect($response->json())->not->toHaveKey('meta');

    $this->getJson(modifierGroupsUrl($product).'?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Pertama');
});

it('shows a modifier group with its modifiers ordered', function () {
    ['product' => $product] = modifierGroupFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    ProductModifier::factory()->forGroup($group)->create(['name' => 'Kedua', 'display_order' => 2]);
    ProductModifier::factory()->forGroup($group)->create(['name' => 'Pertama', 'display_order' => 1]);

    $this->getJson(modifierGroupsUrl($product, '/'.$group->id))
        ->assertOk()
        ->assertJsonCount(2, 'data.modifiers')
        ->assertJsonPath('data.modifiers.0.name', 'Pertama');
});

it('evaluates the update against the resulting state', function () {
    ['product' => $product] = modifierGroupFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create([
        'selection_type' => 'multiple',
        'min_selection' => 0,
        'max_selection' => 3,
        'is_required' => false,
    ]);

    $this->patchJson(modifierGroupsUrl($product, '/'.$group->id), ['is_required' => true])
        ->assertOk()
        ->assertJsonPath('data.min_selection', 1)
        ->assertJsonPath('data.is_required', true);

    $this->patchJson(modifierGroupsUrl($product, '/'.$group->id), ['min_selection' => 2, 'is_required' => false])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['is_required']]);
});

it('refuses to activate a group without enough active modifiers', function () {
    ['product' => $product] = modifierGroupFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create([
        'selection_type' => 'multiple',
        'min_selection' => 0,
        'max_selection' => 3,
        'is_required' => false,
    ]);

    $this->postJson(modifierGroupsUrl($product, '/'.$group->id.'/activate'))
        ->assertStatus(409)
        ->assertJsonPath('code', 'modifier_group_insufficient_modifiers');

    ProductModifier::factory()->forGroup($group)->create();

    $this->postJson(modifierGroupsUrl($product, '/'.$group->id.'/activate'))
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->postJson(modifierGroupsUrl($product, '/'.$group->id.'/deactivate'))
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');
});

it('checks the invariant when raising the min selection of an active group', function () {
    ['product' => $product] = modifierGroupFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create([
        'selection_type' => 'multiple',
        'min_selection' => 0,
        'max_selection' => 3,
        'is_required' => false,
        'status' => CatalogStatus::Active,
    ]);
    ProductModifier::factory()->forGroup($group)->create();

    $this->patchJson(modifierGroupsUrl($product, '/'.$group->id), ['min_selection' => 2, 'is_required' => true])
        ->assertStatus(409)
        ->assertJsonPath('code', 'modifier_group_insufficient_modifiers');

    expect($group->fresh()->min_selection)->toBe(0);
});

it('refuses to switch a group with several defaults to single', function () {
    ['product' => $product] = modifierGroupFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create([
        'selection_type' => 'multiple',
        'min_selection' => 0,
        'max_selection' => 3,
        'is_required' => false,
    ]);
    ProductModifier::factory()->forGroup($group)->default()->create();
    ProductModifier::factory()->forGroup($group)->default()->create();

    $this->patchJson(modifierGroupsUrl($product, '/'.$group->id), ['selection_type' => 'single'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['selection_type']]);
});

it('soft deletes a group together with its modifiers', function () {
    ['product' => $product] = modifierGroupFixture();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    $modifier = ProductModifier::factory()->forGroup($group)->create();

    $this->deleteJson(modifierGroupsUrl($product, '/'.$group->id))->assertStatus(204);

    expect(ProductModifierGroup::query()->find($group->id))->toBeNull()
        ->and(ProductModifierGroup::withTrashed()->find($group->id))->not->toBeNull()
        ->and(ProductModifier::withTrashed()->find($modifier->id)->deleted_at)->not->toBeNull();
});

it('reorders only the submitted groups and rejects groups outside the product', function () {
    ['product' => $product] = modifierGroupFixture();
    $first = ProductModifierGroup::factory()->forProduct($product)->create(['display_order' => 1]);
    $second = ProductModifierGroup::factory()->forProduct($product)->create(['display_order' => 2]);
    $foreign = ProductModifierGroup::factory()->create();

    $this->putJson(modifierGroupsUrl($product, '/order'), [
        'items' => [
            ['group_id' => $first->id, 'display_order' => 5],
            ['group_id' => $second->id, 'display_order' => 4],
        ],
    ])->assertStatus(204);

    expect($first->fresh()->display_order)->toBe(5)
        ->and($second->fresh()->display_order)->toBe(4);

    $this->putJson(modifierGroupsUrl($product, '/order'), [
        'items' => [['group_id' => $foreign->id, 'display_order' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('enforces the modifier group limit per product', function () {
    ['product' => $product] = modifierGroupFixture();
    ProductModifierGroup::factory()->forProduct($product)->count(20)->create();

    $this->postJson(modifierGroupsUrl($product), [
        'name' => 'Kelebihan',
        'selection_type' => 'single',
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'modifier_group_limit_reached');
});

it('returns 404 for a group outside the product or merchant scope', function () {
    ['product' => $product] = modifierGroupFixture();
    $otherProduct = Product::factory()->simple()->create(['merchant_id' => $product->merchant_id]);
    $groupOfOtherProduct = ProductModifierGroup::factory()->forProduct($otherProduct)->create();
    $foreignGroup = ProductModifierGroup::factory()->create();

    $this->getJson(modifierGroupsUrl($product, '/'.$groupOfOtherProduct->id))->assertStatus(404);
    $this->getJson(modifierGroupsUrl($product, '/'.$foreignGroup->id))->assertStatus(404);

    $foreignProduct = Product::factory()->simple()->create();
    $this->getJson(modifierGroupsUrl($foreignProduct))->assertStatus(404);
});
