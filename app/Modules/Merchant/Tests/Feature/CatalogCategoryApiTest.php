<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\CatalogCategory;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Modules\Merchant\Domain\Models\Product;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();
});

/**
 * @param  array<string, mixed>  $merchantAttributes
 * @return array{owner: User, merchant: Merchant}
 */
function catalogOwner(array $merchantAttributes = []): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create($merchantAttributes);

    return compact('owner', 'merchant');
}

const CATEGORIES_URL = '/api/v1/merchant/catalog/categories';

it('requires authentication for the category catalog', function () {
    $this->getJson(CATEGORIES_URL)
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('forbids an outlet employee from managing categories', function () {
    ['merchant' => $merchant] = catalogOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $employee = $this->plainUser();

    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($employee->id)->manager()->create();

    Sanctum::actingAs($employee);

    $this->getJson(CATEGORIES_URL)
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('creates a category with the next display order and a location header', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    CatalogCategory::factory()->forMerchant($merchant->id)->create(['display_order' => 4]);
    Sanctum::actingAs($owner);

    $response = $this->postJson(CATEGORIES_URL, ['name' => 'Minuman'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Minuman')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.display_order', 5);

    expect($response->headers->get('Location'))->toContain('/api/v1/merchant/catalog/categories/');
});

it('rejects a duplicate category name case-insensitively', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Minuman']);
    Sanctum::actingAs($owner);

    $this->postJson(CATEGORIES_URL, ['name' => 'minuman'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.name.0', 'The name has already been taken.');
});

it('allows the same category name for a different merchant', function () {
    CatalogCategory::factory()->create(['name' => 'Minuman']);
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    Sanctum::actingAs($owner);

    $this->postJson(CATEGORIES_URL, ['name' => 'Minuman'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Minuman');
});

it('lists categories ordered by display order with pagination metadata', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Zebra', 'display_order' => 2]);
    CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Anggur', 'display_order' => 1]);
    CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Mangga', 'display_order' => 3]);
    CatalogCategory::factory()->create(); // other merchant
    Sanctum::actingAs($owner);

    $this->getJson(CATEGORIES_URL.'?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Anggur')
        ->assertJsonPath('data.1.name', 'Zebra');
});

it('filters categories by search and status', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Minuman Dingin']);
    CatalogCategory::factory()->forMerchant($merchant->id)->inactive()->create(['name' => 'Makanan']);
    Sanctum::actingAs($owner);

    $this->getJson(CATEGORIES_URL.'?search=minuman')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Minuman Dingin');

    $this->getJson(CATEGORIES_URL.'?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Makanan');
});

it('shows a single category', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Minuman']);
    Sanctum::actingAs($owner);

    $this->getJson(CATEGORIES_URL.'/'.$category->id)
        ->assertOk()
        ->assertJsonPath('data.id', $category->id)
        ->assertJsonPath('data.name', 'Minuman');
});

it('partially updates a category', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create([
        'name' => 'Minuman',
        'description' => 'Awal',
        'display_order' => 1,
    ]);
    Sanctum::actingAs($owner);

    $this->patchJson(CATEGORIES_URL.'/'.$category->id, ['description' => 'Diperbarui'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Minuman')
        ->assertJsonPath('data.description', 'Diperbarui')
        ->assertJsonPath('data.display_order', 1);
});

it('lets a category keep its own name while updating', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Minuman']);
    Sanctum::actingAs($owner);

    $this->patchJson(CATEGORIES_URL.'/'.$category->id, ['name' => 'Minuman', 'description' => 'Tetap'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Minuman');
});

it('soft deletes a category', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Sanctum::actingAs($owner);

    $this->deleteJson(CATEGORIES_URL.'/'.$category->id)->assertStatus(204);

    expect(CatalogCategory::query()->find($category->id))->toBeNull()
        ->and(CatalogCategory::withTrashed()->find($category->id))->not->toBeNull();
});

it('refuses to delete a category that still has products', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Product::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    Sanctum::actingAs($owner);

    $this->deleteJson(CATEGORIES_URL.'/'.$category->id)
        ->assertStatus(409)
        ->assertJsonPath('code', 'catalog_category_in_use');
});

it('deletes a category after its products were removed', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $product = Product::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    $product->delete();
    Sanctum::actingAs($owner);

    $this->deleteJson(CATEGORIES_URL.'/'.$category->id)->assertStatus(204);
});

it('activates and deactivates a category', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->inactive()->create();
    Sanctum::actingAs($owner);

    $this->postJson(CATEGORIES_URL.'/'.$category->id.'/activate')
        ->assertOk()
        ->assertJsonPath('data.status', CatalogStatus::Active->value);

    $this->postJson(CATEGORIES_URL.'/'.$category->id.'/deactivate')
        ->assertOk()
        ->assertJsonPath('data.status', CatalogStatus::Inactive->value);
});

it('reorders only the submitted categories', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $first = CatalogCategory::factory()->forMerchant($merchant->id)->create(['display_order' => 1]);
    $second = CatalogCategory::factory()->forMerchant($merchant->id)->create(['display_order' => 2]);
    $untouched = CatalogCategory::factory()->forMerchant($merchant->id)->create(['display_order' => 3]);
    Sanctum::actingAs($owner);

    $this->putJson(CATEGORIES_URL.'/order', [
        'items' => [
            ['category_id' => $first->id, 'display_order' => 9],
            ['category_id' => $second->id, 'display_order' => 8],
        ],
    ])->assertStatus(204);

    expect($first->fresh()->display_order)->toBe(9)
        ->and($second->fresh()->display_order)->toBe(8)
        ->and($untouched->fresh()->display_order)->toBe(3);
});

it('rejects reorder items outside the merchant scope', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $own = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    $foreign = CatalogCategory::factory()->create();
    Sanctum::actingAs($owner);

    $this->putJson(CATEGORIES_URL.'/order', [
        'items' => [
            ['category_id' => $own->id, 'display_order' => 1],
            ['category_id' => $foreign->id, 'display_order' => 2],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('rejects duplicate ids in a reorder payload', function () {
    ['owner' => $owner, 'merchant' => $merchant] = catalogOwner();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create();
    Sanctum::actingAs($owner);

    $this->putJson(CATEGORIES_URL.'/order', [
        'items' => [
            ['category_id' => $category->id, 'display_order' => 1],
            ['category_id' => $category->id, 'display_order' => 2],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['items.0.category_id', 'items.1.category_id']]);
});

it('returns 404 for a category owned by another merchant', function () {
    ['owner' => $owner] = catalogOwner();
    $foreign = CatalogCategory::factory()->create();
    Sanctum::actingAs($owner);

    $this->getJson(CATEGORIES_URL.'/'.$foreign->id)
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->patchJson(CATEGORIES_URL.'/'.$foreign->id, ['name' => 'Hijack'])
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');

    $this->deleteJson(CATEGORIES_URL.'/'.$foreign->id)->assertStatus(404);

    expect($foreign->fresh()->name)->not->toBe('Hijack');
});

it('returns 404 for an unknown category id', function () {
    ['owner' => $owner] = catalogOwner();
    Sanctum::actingAs($owner);

    $this->getJson(CATEGORIES_URL.'/0192f4b4-0000-7000-8000-000000000000')
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('rejects a non-uuid category parameter', function () {
    ['owner' => $owner] = catalogOwner();
    Sanctum::actingAs($owner);

    $this->getJson(CATEGORIES_URL.'/not-a-uuid')->assertStatus(404);
});
