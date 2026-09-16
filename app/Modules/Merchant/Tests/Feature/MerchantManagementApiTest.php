<?php

use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\MerchantType;
use App\Modules\Merchant\Domain\Models\LegalEntity;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantCategory;
use App\Modules\Merchant\Domain\Models\MerchantDocument;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seedRbac();
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
    $this->actingAsCustomer();

    $this->getJson('/api/v1/merchants')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('lists merchants with resolved service and owner inside the paginated envelope', function () {
    $this->actingAsSuperAdmin();

    $service = $this->newService(['name' => 'JAfood']);
    $owner = $this->plainUser();

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
    $this->actingAsSuperAdmin();

    $service = $this->newService();

    Merchant::factory()->forService($service->id)->active()->create();
    Merchant::factory()->company()->inactive()->create();

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
    $this->actingAsSuperAdmin();

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

    $this->actingAsSuperAdmin();

    $service = $this->newService(['name' => 'JAfood']);
    $category = $this->newServiceCategory(['service_id' => $service->id, 'name' => 'Makanan']);
    $owner = $this->plainUser();

    $province = $this->newProvince(['name' => 'PROVINSI']);
    $regency = $this->newRegency(['province_id' => $province->id, 'name' => 'KABUPATEN']);
    $district = $this->newDistrict(['regency_id' => $regency->id, 'name' => 'KECAMATAN']);
    $village = $this->newVillage(['district_id' => $district->id, 'name' => 'DESA']);

    $legalEntity = LegalEntity::factory()->create(['village_id' => $village->id]);

    $merchant = Merchant::factory()->forService($service->id)->forUser($owner->id)->create([
        'legal_entity_id' => $legalEntity->id,
        'status' => MerchantStatus::Active,
        'logo' => 'merchants/'.$owner->id.'/logo/asset',
    ]);

    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    MerchantOutlet::factory()->create(['merchant_id' => $merchant->id, 'village_id' => $village->id]);
    MerchantDocument::factory()->create(['merchant_id' => $merchant->id, 'object_key' => 'merchants/'.$merchant->id.'/documents/doc']);
    $this->newPayoutAccountForMerchant($merchant->id);

    $this->getJson("/api/v1/merchants/{$merchant->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $merchant->id)
        ->assertJsonPath('data.service.name', 'JAfood')
        ->assertJsonPath('data.owner.id', $owner->id)
        ->assertJsonPath('data.legal_entity.id', $legalEntity->id)
        ->assertJsonPath('data.legal_entity.geography.village', 'DESA')
        ->assertJsonPath('data.categories.0.name', 'Makanan')
        ->assertJsonPath('data.outlets.0.geography.district', 'KECAMATAN')
        ->assertJsonPath('data.documents.0.url', 'https://storage.test/merchants/'.$merchant->id.'/documents/doc')
        ->assertJsonPath('data.logo_url', 'https://storage.test/merchants/'.$owner->id.'/logo/asset')
        ->assertJsonCount(1, 'data.payout_accounts');
});

it('returns a 404 problem when the merchant does not exist', function () {
    $this->actingAsSuperAdmin();

    $this->getJson('/api/v1/merchants/'.fake()->uuid())
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});
