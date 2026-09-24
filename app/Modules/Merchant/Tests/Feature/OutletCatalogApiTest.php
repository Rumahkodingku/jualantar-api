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
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seedRbac();

    $this->app->instance(ObjectStorage::class, new class implements ObjectStorage
    {
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
            return true;
        }

        public function metadata(string $path): StoredObject
        {
            throw new LogicException('Not used in this test.');
        }

        public function delete(string $path): void {}

        public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $options = []): string
        {
            return 'https://storage.test/'.$path;
        }

        public function temporaryUploadUrl(string $path, DateTimeInterface $expiresAt, string $contentType, array $options = []): TemporaryUpload
        {
            return new TemporaryUpload('https://upload.test/'.$path, [], $path, $expiresAt);
        }
    });
});

/**
 * @return array{owner: User, merchant: Merchant, outlet: MerchantOutlet, category: CatalogCategory}
 */
function outletCatalogFixture(): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create();
    $category = CatalogCategory::factory()->forMerchant($merchant->id)->create(['name' => 'Minuman', 'display_order' => 1]);
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    return compact('owner', 'merchant', 'outlet', 'category');
}

/**
 * A fully sellable simple product assigned to the outlet.
 */
function sellableProduct(MerchantOutlet $outlet, CatalogCategory $category, array $productAttributes = [], array $assignmentAttributes = []): Product
{
    $product = Product::factory()->simple()->active()->create([
        'merchant_id' => $outlet->merchant_id,
        'category_id' => $category->id,
        ...$productAttributes,
    ]);

    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create($assignmentAttributes);

    return $product;
}

function outletCatalogUrl(MerchantOutlet $outlet, string $suffix = ''): string
{
    return '/api/v1/merchant/catalog/outlets/'.$outlet->id.'/products'.$suffix;
}

it('requires authentication for the outlet catalog', function () {
    ['outlet' => $outlet] = outletCatalogFixture();

    $this->getJson(outletCatalogUrl($outlet))->assertStatus(401);
});

it('lets the owner, manager and staff read the outlet catalog', function () {
    ['owner' => $owner, 'merchant' => $merchant, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    sellableProduct($outlet, $category);

    Sanctum::actingAs($owner);
    $this->getJson(outletCatalogUrl($outlet))->assertOk()->assertJsonCount(1, 'data');

    foreach ([OutletUserRole::OutletManager, OutletUserRole::OutletStaff] as $role) {
        $user = $this->plainUser();
        MerchantOutletUser::factory()->forOutlet($outlet)->forUser($user->id)->create(['role' => $role]);
        Sanctum::actingAs($user);

        $this->getJson(outletCatalogUrl($outlet))->assertOk()->assertJsonCount(1, 'data');
    }
});

it('forbids an employee from another outlet and hides a foreign outlet', function () {
    ['merchant' => $merchant, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    $otherOutlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $foreignOutlet = MerchantOutlet::factory()->create();
    sellableProduct($outlet, $category);

    $user = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($otherOutlet)->forUser($user->id)->manager()->create();
    Sanctum::actingAs($user);

    $this->getJson(outletCatalogUrl($outlet))
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_scope_forbidden');

    $this->getJson(outletCatalogUrl($foreignOutlet))
        ->assertStatus(404)
        ->assertJsonPath('code', 'outlet_not_found');
});

it('lists only assigned products including inactive ones', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    sellableProduct($outlet, $category, ['name' => 'Aktif']);
    sellableProduct($outlet, $category, ['name' => 'Nonaktif', 'status' => CatalogStatus::Inactive]);
    sellableProduct($outlet, $category, ['name' => 'Habis'], ['availability_status' => ProductAvailabilityStatus::Unavailable]);
    Product::factory()->simple()->active()->create(['merchant_id' => $outlet->merchant_id, 'category_id' => $category->id]);

    Sanctum::actingAs($owner);

    $this->getJson(outletCatalogUrl($outlet))
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
});

it('computes is_sellable from every layer', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    sellableProduct($outlet, $category, ['name' => 'Sellable']);
    sellableProduct($outlet, $category, ['name' => 'Produk Off', 'status' => CatalogStatus::Inactive]);
    sellableProduct($outlet, $category, ['name' => 'Assignment Off'], ['status' => CatalogStatus::Inactive]);
    sellableProduct($outlet, $category, ['name' => 'Habis'], ['availability_status' => ProductAvailabilityStatus::Unavailable]);

    $inactiveCategory = CatalogCategory::factory()->forMerchant($outlet->merchant_id)->inactive()->create();
    sellableProduct($outlet, $inactiveCategory, ['name' => 'Kategori Off']);

    Sanctum::actingAs($owner);

    $response = $this->getJson(outletCatalogUrl($outlet))->assertOk();

    $flags = collect($response->json('data'))->mapWithKeys(
        fn (array $item): array => [$item['product']['name'] => $item['is_sellable']],
    )->all();

    expect($flags)->toHaveCount(5)
        ->and($flags['Sellable'])->toBeTrue()
        ->and($flags['Produk Off'])->toBeFalse()
        ->and($flags['Assignment Off'])->toBeFalse()
        ->and($flags['Habis'])->toBeFalse()
        ->and($flags['Kategori Off'])->toBeFalse();
});

it('requires an active variant for a variable product to be sellable', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    $product = Product::factory()->variable()->active()->create([
        'merchant_id' => $outlet->merchant_id,
        'category_id' => $category->id,
    ]);
    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $this->getJson(outletCatalogUrl($outlet))->assertOk()->assertJsonPath('data.0.is_sellable', false);

    ProductVariant::factory()->forProduct($product)->inactive()->create();
    $this->getJson(outletCatalogUrl($outlet))->assertOk()->assertJsonPath('data.0.is_sellable', false);

    ProductVariant::factory()->forProduct($product)->active()->create();
    $this->getJson(outletCatalogUrl($outlet))->assertOk()->assertJsonPath('data.0.is_sellable', true);
});

it('exposes the product, category, variants, primary media and assignment shape', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    $product = Product::factory()->variable()->active()->create([
        'merchant_id' => $outlet->merchant_id,
        'category_id' => $category->id,
        'name' => 'Ice Cream',
    ]);
    ProductVariant::factory()->forProduct($product)->create(['name' => 'Strawberry']);
    ProductMedia::factory()->forProduct($product)->primary()->create(['alt_text' => 'Foto']);
    OutletProduct::factory()->forOutlet($outlet)->forProduct($product)->create();
    Sanctum::actingAs($owner);

    $response = $this->getJson(outletCatalogUrl($outlet))
        ->assertOk()
        ->assertJsonPath('data.0.product.name', 'Ice Cream')
        ->assertJsonPath('data.0.product.product_type', 'variable')
        ->assertJsonPath('data.0.product.price', null)
        ->assertJsonPath('data.0.category.name', 'Minuman')
        ->assertJsonPath('data.0.variants.0.name', 'Strawberry')
        ->assertJsonPath('data.0.primary_media.alt_text', 'Foto')
        ->assertJsonPath('data.0.assignment.status', 'active')
        ->assertJsonPath('data.0.assignment.availability_status', 'available');

    expect($response->json('data.0.primary_media.url'))->toBeString()->not->toBeEmpty();
});

it('filters the outlet catalog', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    $other = CatalogCategory::factory()->forMerchant($outlet->merchant_id)->create(['name' => 'Makanan']);
    sellableProduct($outlet, $category, ['name' => 'Es Teh']);
    sellableProduct($outlet, $other, ['name' => 'Nasi Goreng']);
    sellableProduct($outlet, $other, ['name' => 'Mie'], ['availability_status' => ProductAvailabilityStatus::Unavailable]);
    sellableProduct($outlet, $other, ['name' => 'Kopi'], ['status' => CatalogStatus::Inactive]);
    Sanctum::actingAs($owner);

    $this->getJson(outletCatalogUrl($outlet).'?search=teh')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.product.name', 'Es Teh');

    $this->getJson(outletCatalogUrl($outlet).'?category_id='.$other->id)
        ->assertOk()->assertJsonCount(3, 'data');

    $this->getJson(outletCatalogUrl($outlet).'?availability=unavailable')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.product.name', 'Mie');

    $this->getJson(outletCatalogUrl($outlet).'?status=inactive')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.product.name', 'Kopi');

    $this->getJson(outletCatalogUrl($outlet).'?sort=name&order=desc')
        ->assertOk()->assertJsonPath('data.0.product.name', 'Nasi Goreng');
});

it('orders by category and then by the outlet display order', function () {
    ['owner' => $owner, 'outlet' => $outlet] = outletCatalogFixture();
    $firstCategory = CatalogCategory::factory()->forMerchant($outlet->merchant_id)->create(['name' => 'Pertama', 'display_order' => 1]);
    $secondCategory = CatalogCategory::factory()->forMerchant($outlet->merchant_id)->create(['name' => 'Kedua', 'display_order' => 2]);

    sellableProduct($outlet, $secondCategory, ['name' => 'B2'], ['display_order' => 1]);
    sellableProduct($outlet, $firstCategory, ['name' => 'A2'], ['display_order' => 2]);
    sellableProduct($outlet, $firstCategory, ['name' => 'A1'], ['display_order' => 1]);
    Sanctum::actingAs($owner);

    $names = collect($this->getJson(outletCatalogUrl($outlet))->assertOk()->json('data'))
        ->pluck('product.name')
        ->all();

    expect($names)->toBe(['A1', 'A2', 'B2']);
});

it('reorders the outlet catalog without touching the master display order', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    $first = sellableProduct($outlet, $category, ['name' => 'Satu', 'display_order' => 50], ['display_order' => 1]);
    $second = sellableProduct($outlet, $category, ['name' => 'Dua', 'display_order' => 60], ['display_order' => 2]);
    Sanctum::actingAs($owner);

    $this->putJson(outletCatalogUrl($outlet, '/order'), [
        'items' => [
            ['product_id' => $first->id, 'display_order' => 2],
            ['product_id' => $second->id, 'display_order' => 1],
        ],
    ])->assertStatus(204);

    expect($first->fresh()->display_order)->toBe(50)
        ->and($second->fresh()->display_order)->toBe(60)
        ->and(OutletProduct::query()->where('product_id', $first->id)->value('display_order'))->toBe(2)
        ->and(OutletProduct::query()->where('product_id', $second->id)->value('display_order'))->toBe(1);
});

it('rejects reordering unassigned products', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    $assigned = sellableProduct($outlet, $category);
    $unassigned = Product::factory()->simple()->create(['merchant_id' => $outlet->merchant_id, 'category_id' => $category->id]);
    Sanctum::actingAs($owner);

    $this->putJson(outletCatalogUrl($outlet, '/order'), [
        'items' => [
            ['product_id' => $assigned->id, 'display_order' => 1],
            ['product_id' => $unassigned->id, 'display_order' => 2],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('lets a manager reorder but forbids staff', function () {
    ['merchant' => $merchant, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    $product = sellableProduct($outlet, $category, [], ['display_order' => 1]);

    $manager = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($manager->id)->manager()->create();
    Sanctum::actingAs($manager);

    $this->putJson(outletCatalogUrl($outlet, '/order'), [
        'items' => [['product_id' => $product->id, 'display_order' => 5]],
    ])->assertStatus(204);

    $staff = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($staff->id)->staff()->create();
    Sanctum::actingAs($staff);

    $this->putJson(outletCatalogUrl($outlet, '/order'), [
        'items' => [['product_id' => $product->id, 'display_order' => 6]],
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_capability_forbidden');
});

it('exposes only active modifier groups and modifiers in the outlet catalog', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();
    $product = sellableProduct($outlet, $category);

    $active = ProductModifierGroup::factory()->forProduct($product)->active()->create(['name' => 'Aktif', 'display_order' => 1]);
    ProductModifier::factory()->forGroup($active)->create(['name' => 'Aktif Mod', 'display_order' => 1]);
    ProductModifier::factory()->forGroup($active)->inactive()->create(['name' => 'Nonaktif Mod', 'display_order' => 2]);
    ProductModifierGroup::factory()->forProduct($product)->inactive()->create(['name' => 'Nonaktif Group', 'display_order' => 2]);

    Sanctum::actingAs($owner);

    $this->getJson(outletCatalogUrl($outlet))
        ->assertOk()
        ->assertJsonCount(1, 'data.0.modifier_groups')
        ->assertJsonPath('data.0.modifier_groups.0.name', 'Aktif')
        ->assertJsonCount(1, 'data.0.modifier_groups.0.modifiers')
        ->assertJsonPath('data.0.modifier_groups.0.modifiers.0.name', 'Aktif Mod')
        ->assertJsonPath('data.0.is_sellable', true);
});

it('eager loads modifier groups to avoid an N+1 in the outlet catalog', function () {
    ['owner' => $owner, 'outlet' => $outlet, 'category' => $category] = outletCatalogFixture();

    $products = collect(range(1, 5))
        ->map(fn (int $i) => sellableProduct($outlet, $category, ['name' => "Produk {$i}"]));

    Sanctum::actingAs($owner);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson(outletCatalogUrl($outlet))->assertOk()->assertJsonCount(5, 'data');
    $baseline = count(DB::getQueryLog());
    DB::disableQueryLog();

    foreach ($products as $product) {
        $group = ProductModifierGroup::factory()->forProduct($product)->active()->create();
        ProductModifier::factory()->forGroup($group)->count(3)->create();
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson(outletCatalogUrl($outlet))->assertOk()->assertJsonCount(5, 'data');
    $withModifiers = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($withModifiers - $baseline)->toBeLessThanOrEqual(3);
});
