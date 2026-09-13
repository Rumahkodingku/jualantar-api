<?php

use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Domain\Models\Regency;
use App\Modules\Geography\Domain\Models\Village;
use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\MerchantType;
use App\Modules\Merchant\Domain\Models\LegalEntity;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantCategory;
use App\Modules\Merchant\Domain\Models\MerchantDocument;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function fakeObjectStorage(): ObjectStorage
{
    return new class implements ObjectStorage
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
            throw new LogicException('Not used in this test.');
        }
    };
}

it('rejects a guest from listing merchants', function () {
    $this->getJson('/api/v1/merchants')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('forbids a user without the merchant view permission', function () {
    Sanctum::actingAs(User::factory()->customer()->create());

    $this->getJson('/api/v1/merchants')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('lists merchants with resolved service and owner inside the paginated envelope', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create(['name' => 'JAfood']);
    $owner = User::factory()->create();

    Merchant::factory()->forService($service->id)->forUser($owner->id)->create([
        'business_name' => 'Warung Bu Siti',
    ]);
    Merchant::factory()->create();

    $this->getJson('/api/v1/merchants')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure([
            'data' => [['id', 'business_name', 'slug', 'type', 'status', 'service', 'owner', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);

    $this->getJson('/api/v1/merchants?search=warung')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.service.name', 'JAfood')
        ->assertJsonPath('data.0.owner.id', $owner->id);
});

it('filters merchants by status, type and service', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create();

    Merchant::factory()->forService($service->id)->active()->create();
    Merchant::factory()->company()->pending()->create();

    $this->getJson('/api/v1/merchants?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', MerchantStatus::Active->value);

    $this->getJson('/api/v1/merchants?type=company')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', MerchantType::Company->value);

    $this->getJson('/api/v1/merchants?service_id='.$service->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns a validation problem for invalid index query params', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->getJson('/api/v1/merchants?status=bogus')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.status.0', 'The selected status is invalid.');

    $this->getJson('/api/v1/merchants?per_page=0')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('shows a merchant detail with relations and cross-module data resolved', function () {
    $this->app->bind(ObjectStorage::class, fn () => fakeObjectStorage());

    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $service = Service::factory()->create(['name' => 'JAfood']);
    $category = ServiceCategory::factory()->create(['service_id' => $service->id, 'name' => 'Makanan']);
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();

    $province = Province::factory()->create(['name' => 'PROVINSI']);
    $regency = Regency::factory()->create(['province_id' => $province->id, 'name' => 'KABUPATEN']);
    $district = District::factory()->create(['regency_id' => $regency->id, 'name' => 'KECAMATAN']);
    $village = Village::factory()->create(['district_id' => $district->id, 'name' => 'DESA']);

    $legalEntity = LegalEntity::factory()->create(['village_id' => $village->id]);

    $merchant = Merchant::factory()->forService($service->id)->forUser($owner->id)->create([
        'legal_entity_id' => $legalEntity->id,
        'status' => MerchantStatus::Active,
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now(),
        'logo' => 'merchants/'.$owner->id.'/logo/asset',
    ]);

    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    MerchantOutlet::factory()->create(['merchant_id' => $merchant->id, 'village_id' => $village->id]);
    MerchantDocument::factory()->create(['merchant_id' => $merchant->id, 'object_key' => 'merchants/'.$merchant->id.'/documents/doc']);
    PayoutAccount::factory()->forOwner(PayoutOwnerType::Merchant, $merchant->id)->create();

    $this->getJson("/api/v1/merchants/{$merchant->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $merchant->id)
        ->assertJsonPath('data.service.name', 'JAfood')
        ->assertJsonPath('data.owner.id', $owner->id)
        ->assertJsonPath('data.reviewer.id', $reviewer->id)
        ->assertJsonPath('data.legal_entity.id', $legalEntity->id)
        ->assertJsonPath('data.legal_entity.geography.village', 'DESA')
        ->assertJsonPath('data.categories.0.name', 'Makanan')
        ->assertJsonPath('data.outlets.0.geography.district', 'KECAMATAN')
        ->assertJsonPath('data.documents.0.url', 'https://storage.test/merchants/'.$merchant->id.'/documents/doc')
        ->assertJsonPath('data.logo_url', 'https://storage.test/merchants/'.$owner->id.'/logo/asset')
        ->assertJsonCount(1, 'data.payout_accounts');
});

it('returns a 404 problem when the merchant does not exist', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->getJson('/api/v1/merchants/'.fake()->uuid())
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});
