<?php

use App\Modules\Merchant\Domain\Enums\MerchantType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantCategory;
use App\Modules\Merchant\Domain\Models\MerchantDocument;
use App\Modules\Merchant\Domain\Models\MerchantIdentity;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->app->bind(ObjectStorage::class, fn () => fakeRegistrationObjectStorage());
});

function fakeRegistrationObjectStorage(): ObjectStorage
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
            return new TemporaryUpload('https://upload.test/'.$path, ['Content-Type' => $contentType], $path, $expiresAt);
        }
    };
}

/**
 * @return array<string, mixed>
 */
function outletPayload(int $villageId, array $overrides = []): array
{
    return array_merge([
        'name' => 'Outlet Utama',
        'phone' => '081234567890',
        'email' => 'outlet@example.test',
        'address' => 'Jl. Merdeka No. 1',
        'province_id' => 1,
        'regency_id' => 1,
        'district_id' => 1,
        'village_id' => $villageId,
        'postal_code' => '12345',
        'latitude' => -0.5,
        'longitude' => 117.1,
        'service_area_type' => 'radius',
        'service_radius_km' => 5,
        'operating_hours' => [
            'monday' => [['open' => '08:00', 'close' => '17:00']],
        ],
    ], $overrides);
}

it('rejects a guest from accessing registration', function () {
    $this->getJson('/api/v1/merchants/registration')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('creates an empty draft registration for the authenticated user', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/merchants/registration')
        ->assertCreated()
        ->assertJsonStructure(['data' => ['merchant_id', 'status']])
        ->assertJsonPath('data.status', 'draft');

    $merchant = Merchant::query()->where('user_id', $user->id)->first();

    expect($merchant)->not->toBeNull()
        ->and($merchant->business_name)->toBeNull()
        ->and($merchant->service_id)->toBeNull();
});

it('rejects a duplicate registration for the same user', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();

    $this->postJson('/api/v1/merchants/registration')
        ->assertStatus(409)
        ->assertJsonPath('code', 'merchant_registration_already_exists');
});

it('returns 404 when the user has no registration', function () {
    Sanctum::actingAs($this->plainUser());

    $this->getJson('/api/v1/merchants/registration')
        ->assertStatus(404)
        ->assertJsonPath('code', 'merchant_registration_not_found');
});

it('only exposes the registration owned by the authenticated user', function () {
    $owner = $this->plainUser();
    $other = $this->plainUser();
    Merchant::factory()->blankDraft($owner->id)->create();

    Sanctum::actingAs($other);

    $this->getJson('/api/v1/merchants/registration')->assertStatus(404);
});

it('updates the draft and generates a slug from the business name', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();

    $this->patchJson('/api/v1/merchants/registration', [
        'business_name' => 'Warung Borneo',
        'type' => 'individual',
        'description' => 'Warung makanan lokal',
    ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'warung-borneo')
        ->assertJsonPath('data.type', 'individual');
});

it('appends a suffix when the generated slug already exists', function () {
    $other = $this->plainUser();
    Merchant::factory()->forUser($other->id)->create([
        'business_name' => 'Warung Borneo',
        'slug' => 'warung-borneo',
    ]);

    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();

    $this->patchJson('/api/v1/merchants/registration', ['business_name' => 'Warung Borneo'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'warung-borneo-2');
});

it('saves and updates the merchant identity', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();

    $this->putJson('/api/v1/merchants/registration/identity', [
        'id_type' => 'ktp',
        'id_number' => '6171xxxxxxxxxxxx',
        'full_name' => 'Budi',
        'birth_date' => '2000-01-01',
    ])
        ->assertOk()
        ->assertJsonPath('data.identity.full_name', 'Budi');

    $this->putJson('/api/v1/merchants/registration/identity', [
        'id_type' => 'ktp',
        'id_number' => '6171xxxxxxxxxxxx',
        'full_name' => 'Budi Santoso',
    ])
        ->assertOk()
        ->assertJsonPath('data.identity.full_name', 'Budi Santoso');

    expect($merchant->identity()->count())->toBe(1);
});

it('saves a legal entity and validates the village through the geography contract', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create(['type' => MerchantType::Company]);
    $village = $this->newVillage();

    $payload = [
        'entity_type' => 'pt',
        'name' => 'PT Maju Jaya',
        'nib' => '123456789',
        'npwp' => '12.345.678.9-012.345',
        'address' => 'Jl. Sudirman',
        'province_id' => 1,
        'regency_id' => 1,
        'district_id' => 1,
        'village_id' => $village->id,
        'postal_code' => '12345',
    ];

    $this->putJson('/api/v1/merchants/registration/legal-entity', $payload)
        ->assertOk()
        ->assertJsonPath('data.legal_entity.name', 'PT Maju Jaya');

    $this->putJson('/api/v1/merchants/registration/legal-entity', array_merge($payload, ['village_id' => 999999]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_geography');
});

it('saves an active service and its categories', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();

    $service = $this->newService(['is_active' => true]);
    $category = $this->newServiceCategory(['service_id' => $service->id, 'is_active' => true]);

    $this->putJson('/api/v1/merchants/registration/service', ['service_id' => $service->id])
        ->assertOk()
        ->assertJsonPath('data.service.id', $service->id);

    $this->putJson('/api/v1/merchants/registration/categories', ['category_ids' => [$category->id]])
        ->assertOk()
        ->assertJsonPath('data.categories.0.category_id', $category->id);
});

it('rejects an inactive service', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();

    $service = $this->newService(['is_active' => false]);

    $this->putJson('/api/v1/merchants/registration/service', ['service_id' => $service->id])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_service');
});

it('rejects a category that belongs to another service', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();

    $service = $this->newService();
    $otherService = $this->newService();
    $category = $this->newServiceCategory(['service_id' => $otherService->id]);

    $merchant->update(['service_id' => $service->id]);

    $this->putJson('/api/v1/merchants/registration/categories', ['category_ids' => [$category->id]])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_category');
});

it('validates the category count through the request', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();

    $this->putJson('/api/v1/merchants/registration/categories', [
        'category_ids' => [
            (string) fake()->uuid(),
            (string) fake()->uuid(),
            (string) fake()->uuid(),
            (string) fake()->uuid(),
        ],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('creates, updates and deletes outlets while validating geography and operating hours', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();

    $province = $this->newProvince();
    $regency = $this->newRegency(['province_id' => $province->id]);
    $district = $this->newDistrict(['regency_id' => $regency->id]);
    $village = $this->newVillage(['district_id' => $district->id]);

    $this->postJson('/api/v1/merchants/registration/outlets', outletPayload($village->id))
        ->assertCreated()
        ->assertJsonPath('data.outlets.0.status', OutletStatus::Active->value);

    $outlet = MerchantOutlet::query()->firstOrFail();

    $this->patchJson("/api/v1/merchants/registration/outlets/{$outlet->id}", ['name' => 'Outlet Baru'])
        ->assertOk()
        ->assertJsonPath('data.outlets.0.name', 'Outlet Baru');

    $this->postJson('/api/v1/merchants/registration/outlets', outletPayload($village->id, ['village_id' => 999999]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_geography');

    $this->postJson('/api/v1/merchants/registration/outlets', outletPayload($village->id, [
        'operating_hours' => ['monday' => [['open' => '8am', 'close' => '17:00']]],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');

    $this->deleteJson("/api/v1/merchants/registration/outlets/{$outlet->id}")
        ->assertNoContent();

    expect(MerchantOutlet::query()->count())->toBe(0);
});

it('issues a presigned upload and stores the logo object key', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();

    $response = $this->postJson('/api/v1/merchants/registration/uploads', [
        'purpose' => 'logo',
        'file_name' => 'logo.png',
        'mime_type' => 'image/png',
        'file_size' => 102400,
    ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['object_key', 'upload_url', 'headers', 'expires_at']]);

    $objectKey = $response->json('data.object_key');

    expect($objectKey)->toStartWith("merchants/{$merchant->id}/logo/")
        ->and($merchant->refresh()->logo)->toBe($objectKey);
});

it('attaches a document only when the object key belongs to the merchant', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();

    $this->postJson('/api/v1/merchants/registration/documents', [
        'document_type' => 'ktp',
        'object_key' => "merchants/{$merchant->id}/documents/ktp.jpg",
        'file_name' => 'ktp.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 245678,
    ])
        ->assertCreated()
        ->assertJsonPath('data.document_type', 'ktp');

    $this->postJson('/api/v1/merchants/registration/documents', [
        'document_type' => 'ktp',
        'object_key' => 'merchants/other/documents/ktp.jpg',
        'file_name' => 'ktp.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 245678,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');
});

it('accepts the extended document types', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();

    $types = ['swafoto', 'rekening', 'foto_outlet', 'identitas_direktur', 'izin_usaha'];

    foreach ($types as $type) {
        $this->postJson('/api/v1/merchants/registration/documents', [
            'document_type' => $type,
            'object_key' => "merchants/{$merchant->id}/documents/{$type}.jpg",
            'file_name' => "{$type}.jpg",
            'mime_type' => 'image/jpeg',
            'file_size' => 245678,
        ])
            ->assertCreated()
            ->assertJsonPath('data.document_type', $type);
    }

    expect($merchant->refresh()->documents)->toHaveCount(count($types));
});

it('deletes a document that belongs to the merchant', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();
    $document = MerchantDocument::factory()->create([
        'merchant_id' => $merchant->id,
        'object_key' => "merchants/{$merchant->id}/documents/ktp.jpg",
    ]);

    $this->deleteJson("/api/v1/merchants/registration/documents/{$document->id}")
        ->assertNoContent();

    expect(MerchantDocument::query()->find($document->id))->toBeNull();
});

it('refuses to delete a document owned by another merchant', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();
    $otherMerchant = Merchant::factory()->create();
    $document = MerchantDocument::factory()->create(['merchant_id' => $otherMerchant->id]);

    $this->deleteJson("/api/v1/merchants/registration/documents/{$document->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'merchant_registration_not_found');

    expect(MerchantDocument::query()->find($document->id))->not->toBeNull();
});

it('saves a payout account through the payout contract', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();
    $bank = $this->newBank();

    $this->putJson('/api/v1/merchants/registration/payout-account', [
        'bank_id' => $bank->id,
        'account_number' => '1234567890',
        'account_name' => 'Budi Santoso',
    ])
        ->assertOk()
        ->assertJsonPath('data.payout_accounts.0.bank_id', $bank->id)
        ->assertJsonPath('data.payout_accounts.0.bank_name', $bank->name);

    $this->putJson('/api/v1/merchants/registration/payout-account', [
        'bank_id' => 999999,
        'account_number' => '1234567890',
        'account_name' => 'Budi Santoso',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_payout_account');
});

it('returns a complete review payload with resolved cross-module data', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();

    $service = $this->newService(['name' => 'JAfood']);
    $category = $this->newServiceCategory(['service_id' => $service->id, 'name' => 'Makanan']);
    $village = $this->newVillage(['name' => 'DESA']);
    $bank = $this->newBank(['name' => 'Bank Test']);

    $merchant->update([
        'business_name' => 'Warung Borneo',
        'slug' => 'warung-borneo',
        'type' => MerchantType::Individual,
        'service_id' => $service->id,
        'logo' => "merchants/{$merchant->id}/logo/asset",
    ]);

    MerchantIdentity::factory()->create(['merchant_id' => $merchant->id]);
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    MerchantOutlet::factory()->create(['merchant_id' => $merchant->id, 'village_id' => $village->id]);
    $this->newPayoutAccountForMerchant($merchant->id, ['bank_id' => $bank->id]);

    $this->getJson('/api/v1/merchants/registration/review')
        ->assertOk()
        ->assertJsonPath('data.id', $merchant->id)
        ->assertJsonPath('data.service.name', 'JAfood')
        ->assertJsonPath('data.categories.0.name', 'Makanan')
        ->assertJsonPath('data.outlets.0.geography.village', 'DESA')
        ->assertJsonPath('data.payout_accounts.0.bank_name', 'Bank Test')
        ->assertJsonPath('data.logo_url', 'https://storage.test/merchants/'.$merchant->id.'/logo/asset');
});

it('reports incomplete registrations on submit', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create();

    $this->postJson('/api/v1/merchants/registration/submit')
        ->assertStatus(422)
        ->assertJsonPath('code', 'registration_incomplete')
        ->assertJsonPath('errors.business_name.0', 'The business name is required before submitting.');
});

it('submits a complete registration and transitions draft to pending', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    $merchant = Merchant::factory()->blankDraft($user->id)->create();

    $service = $this->newService();
    $category = $this->newServiceCategory(['service_id' => $service->id]);
    $village = $this->newVillage();
    $bank = $this->newBank();

    $merchant->update([
        'business_name' => 'Warung Borneo',
        'slug' => 'warung-borneo',
        'type' => MerchantType::Individual,
        'service_id' => $service->id,
    ]);

    MerchantIdentity::factory()->create(['merchant_id' => $merchant->id]);
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    MerchantOutlet::factory()->create(['merchant_id' => $merchant->id, 'village_id' => $village->id, 'status' => OutletStatus::Active]);
    $this->newPayoutAccountForMerchant($merchant->id, ['bank_id' => $bank->id]);

    $this->postJson('/api/v1/merchants/registration/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    expect($merchant->refresh()->status->value)->toBe('pending');

    // Double submit is idempotent and does not create a new merchant.
    $this->postJson('/api/v1/merchants/registration/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    expect(Merchant::query()->count())->toBe(1);
});

it('rejects mutations once the registration is no longer a draft', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);
    Merchant::factory()->blankDraft($user->id)->create(['status' => 'pending']);

    $this->patchJson('/api/v1/merchants/registration', ['business_name' => 'Nope'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_registration_state');
});
