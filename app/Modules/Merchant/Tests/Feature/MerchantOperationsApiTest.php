<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Modules\Storage\Contracts\DataTransferObjects\StoredObject;
use App\Modules\Storage\Contracts\DataTransferObjects\TemporaryUpload;
use App\Modules\Storage\Contracts\ObjectStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

function fakeOperationsObjectStorage(): ObjectStorage
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

beforeEach(function () {
    $this->seedRbac();
});

/**
 * Create an owner with the merchant role and an owned merchant.
 *
 * @param  array<string, mixed>  $merchantAttributes
 * @return array{owner: User, merchant: Merchant}
 */
function operationsOwner(array $merchantAttributes = []): array
{
    $owner = test()->merchantUser();
    $merchant = Merchant::factory()->forUser($owner->id)->active()->create($merchantAttributes);

    return compact('owner', 'merchant');
}

/**
 * Create an employee with an outlet-scoped assignment. The role lives only on
 * merchant_outlet_users; no global Spatie role is granted.
 *
 * @return array{user: User, outlet: MerchantOutlet}
 */
function operationsEmployee(Merchant $merchant, string $role, ?MerchantOutlet $outlet = null): array
{
    $outlet ??= MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $user = test()->plainUser();

    MerchantOutletUser::factory()
        ->forOutlet($outlet)
        ->forUser($user->id)
        ->create(['role' => $role]);

    return compact('user', 'outlet');
}

it('requires authentication for merchant operations', function () {
    $this->getJson('/api/v1/merchant/operations')->assertStatus(401);
});

it('distinguishes unauthenticated from unauthorized with problem codes', function () {
    $this->getJson('/api/v1/merchant/operations')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');

    Sanctum::actingAs($this->plainUser());

    $this->getJson('/api/v1/merchant/operations')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('exposes outlet assignments in the auth me contract', function () {
    ['merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager', $outlet);

    Sanctum::actingAs($manager);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.outlet_assignments.0.outlet_id', $outlet->id)
        ->assertJsonPath('data.outlet_assignments.0.role', 'outlet_manager');
});

it('denies a user without the operations permission', function () {
    Sanctum::actingAs($this->plainUser());

    $this->getJson('/api/v1/merchant/operations')->assertStatus(403);
});

it('returns the operational summary for the owner', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/merchant/operations')
        ->assertOk()
        ->assertJsonPath('data.merchant.id', $merchant->id)
        ->assertJsonPath('data.merchant.status', 'active')
        ->assertJsonPath('data.operational.status', 'active');
});

it('lets the owner activate, suspend and reactivate the merchant', function () {
    ['owner' => $owner] = operationsOwner(['status' => 'inactive']);
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/merchant/operations/activate')
        ->assertOk()->assertJsonPath('data.status', 'active');

    $this->postJson('/api/v1/merchant/operations/suspend', ['reason' => 'Stok habis'])
        ->assertOk()->assertJsonPath('data.status', 'suspended');

    $this->postJson('/api/v1/merchant/operations/reactivate')
        ->assertOk()->assertJsonPath('data.status', 'active');
});

it('rejects an invalid merchant status transition', function () {
    ['owner' => $owner] = operationsOwner();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/merchant/operations/activate')
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_status_transition');
});

it('denies a manager from changing merchant status', function () {
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager');
    Sanctum::actingAs($manager);

    $this->postJson('/api/v1/merchant/operations/suspend')->assertStatus(403);
});

it('updates the operational profile as the owner and regenerates the slug', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    Sanctum::actingAs($owner);

    $this->patchJson('/api/v1/merchant/operations/profile', [
        'business_name' => 'Warung Baru',
        'description' => 'Deskripsi baru',
        'operational_phone' => '08123456789',
        'operational_email' => 'ops@example.test',
        'website' => 'https://example.test',
    ])
        ->assertOk()
        ->assertJsonPath('data.business_name', 'Warung Baru')
        ->assertJsonPath('data.slug', 'warung-baru')
        ->assertJsonPath('data.operational_email', 'ops@example.test');

    expect($merchant->refresh()->operational_email)->toBe('ops@example.test');
});

it('returns the operational profile for an assigned manager', function () {
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager');
    Sanctum::actingAs($manager);

    $this->getJson('/api/v1/merchant/operations/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $merchant->id);
});

it('denies an unauthorized profile mutation', function () {
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $staff] = operationsEmployee($merchant, 'outlet_staff');
    Sanctum::actingAs($staff);

    $this->patchJson('/api/v1/merchant/operations/profile', ['business_name' => 'X'])
        ->assertStatus(403);
});

/**
 * @return array<string, mixed>
 */
function operationalOutletPayload(array $region, array $overrides = []): array
{
    return array_merge([
        'name' => 'Outlet Putussibau',
        'phone' => '081234567890',
        'email' => 'outlet@example.test',
        'address' => 'Jl. Diponegoro',
        'province_id' => $region['province']->id,
        'regency_id' => $region['regency']->id,
        'district_id' => $region['district']->id,
        'village_id' => $region['village']->id,
        'postal_code' => '78711',
        'latitude' => 0.8421,
        'longitude' => 112.9321,
    ], $overrides);
}

it('lists all outlets for the owner', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    MerchantOutlet::factory()->count(2)->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/merchant/operations/outlets')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('creates an outlet for the owner with default service area', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $region = $this->region();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/merchant/operations/outlets', operationalOutletPayload($region))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Outlet Putussibau')
        ->assertJsonPath('data.service_area_type', 'radius')
        ->assertJsonPath('data.status', 'active');

    $outlet = $merchant->outlets()->firstOrFail();
    expect($outlet->service_radius_km)->toBe('5.00')
        ->and($outlet->village_id)->toBe($region['village']->id);
});

it('updates and toggles an outlet as the owner', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/merchant/operations/outlets/{$outlet->id}", ['name' => 'Outlet Baru'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Outlet Baru');

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/deactivate")
        ->assertOk()->assertJsonPath('data.status', 'inactive');

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/activate")
        ->assertOk()->assertJsonPath('data.status', 'active');
});

it('limits a manager to assigned outlets', function () {
    ['merchant' => $merchant] = operationsOwner();
    $assigned = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $other = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager', $assigned);
    Sanctum::actingAs($manager);

    $this->getJson('/api/v1/merchant/operations/outlets')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $assigned->id);

    $this->getJson("/api/v1/merchant/operations/outlets/{$other->id}")
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_scope_forbidden');
});

it('hides a foreign merchant outlet behind a 404', function () {
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager');

    ['merchant' => $foreignMerchant] = operationsOwner();
    $foreignOutlet = MerchantOutlet::factory()->create(['merchant_id' => $foreignMerchant->id]);

    Sanctum::actingAs($manager);

    $this->getJson("/api/v1/merchant/operations/outlets/{$foreignOutlet->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 'outlet_not_found');
});

it('denies outlet creation for a manager', function () {
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager');
    Sanctum::actingAs($manager);

    $this->postJson('/api/v1/merchant/operations/outlets', [])->assertStatus(403);
});

it('assigns, lists, changes and removes outlet users as the owner', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $target = $this->plainUser();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users", [
        'user_id' => $target->id,
        'role' => 'outlet_manager',
    ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'outlet_manager')
        ->assertJsonPath('data.user.id', $target->id);

    expect($target->fresh()->hasRole('outlet_manager'))->toBeFalse();

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users")
        ->assertOk()
        ->assertJsonPath('data.0.user.id', $target->id);

    $this->patchJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users/{$target->id}", [
        'role' => 'outlet_staff',
    ])
        ->assertOk()
        ->assertJsonPath('data.role', 'outlet_staff');

    expect($target->fresh()->hasRole('outlet_staff'))->toBeFalse()
        ->and($target->fresh()->hasRole('outlet_manager'))->toBeFalse();

    $this->deleteJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users/{$target->id}")
        ->assertNoContent();

    expect(MerchantOutletUser::query()->count())->toBe(0)
        ->and($target->fresh()->hasRole('outlet_staff'))->toBeFalse();
});

it('prevents a duplicate outlet assignment', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $target = $this->plainUser();
    Sanctum::actingAs($owner);

    $payload = ['user_id' => $target->id, 'role' => 'outlet_staff'];

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users", $payload)->assertCreated();
    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users", $payload)
        ->assertStatus(409)
        ->assertJsonPath('code', 'duplicate_outlet_assignment');
});

it('does not allow assigning the owner as an employee', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users", [
        'user_id' => $owner->id,
        'role' => 'outlet_manager',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'business_rule_violation');
});

it('returns a 404 when assigning an unknown identity user', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users", [
        'user_id' => '00000000-0000-0000-0000-000000000000',
        'role' => 'outlet_staff',
    ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'user_not_found');
});

it('prevents a manager from managing users of a foreign outlet', function () {
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager');

    ['merchant' => $foreignMerchant] = operationsOwner();
    $foreignOutlet = MerchantOutlet::factory()->create(['merchant_id' => $foreignMerchant->id]);
    $target = $this->plainUser();
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/merchant/operations/outlets/{$foreignOutlet->id}/users", [
        'user_id' => $target->id,
        'role' => 'outlet_staff',
    ])
        ->assertStatus(404)
        ->assertJsonPath('code', 'outlet_not_found');
});

it('updates and reads operating hours', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours", [
        'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '22:00'],
        'sunday' => ['is_open' => false],
    ])
        ->assertOk()
        ->assertJsonPath('data.monday.is_open', true)
        ->assertJsonPath('data.monday.open', '08:00')
        ->assertJsonPath('data.sunday.is_open', false);

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours")
        ->assertOk()
        ->assertJsonPath('data.monday.close', '22:00');
});

it('rejects an invalid operating hours range', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours", [
        'monday' => ['is_open' => true, 'open' => '22:00', 'close' => '08:00'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_operating_hours');
});

it('rejects unknown day keys and open days without times', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours", [
        'funday' => ['is_open' => true, 'open' => '08:00', 'close' => '09:00'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_operating_hours');

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours", [
        'monday' => ['is_open' => true],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_operating_hours');
});

it('lets a manager update operating hours on an assigned outlet', function () {
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $manager, 'outlet' => $outlet] = operationsEmployee($merchant, 'outlet_manager');
    Sanctum::actingAs($manager);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours", [
        'monday' => ['is_open' => true, 'open' => '09:00', 'close' => '21:00'],
    ])
        ->assertOk()
        ->assertJsonPath('data.monday.open', '09:00');
});

function outletAtRegion(Merchant $merchant, array $region): MerchantOutlet
{
    return MerchantOutlet::factory()->create([
        'merchant_id' => $merchant->id,
        'province_id' => $region['province']->id,
        'regency_id' => $region['regency']->id,
        'district_id' => $region['district']->id,
        'village_id' => $region['village']->id,
    ]);
}

it('updates a radius service area', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/service-area", [
        'type' => 'radius',
        'radius_km' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.type', 'radius')
        ->assertJsonPath('data.radius_km', '5.00');

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/service-area")
        ->assertOk()
        ->assertJsonPath('data.type', 'radius');
});

it('updates every region service area type', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $region = $this->region();
    $outlet = outletAtRegion($merchant, $region);
    Sanctum::actingAs($owner);

    $cases = [
        'province' => ['province_id' => $region['province']->id],
        'regency' => ['regency_id' => $region['regency']->id],
        'district' => ['district_id' => $region['district']->id],
        'village' => ['village_id' => $region['village']->id],
    ];

    foreach ($cases as $type => $payload) {
        $this->putJson(
            "/api/v1/merchant/operations/outlets/{$outlet->id}/service-area",
            ['type' => $type] + $payload,
        )
            ->assertOk()
            ->assertJsonPath('data.type', $type);
    }
});

it('rejects a radius without radius_km and irrelevant region fields', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/service-area", [
        'type' => 'radius',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_service_area');

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/service-area", [
        'type' => 'radius',
        'radius_km' => 5,
        'province_id' => 1,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_service_area');
});

it('rejects a region service area that does not match the outlet region', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outletRegion = $this->region();
    $otherRegion = $this->region();
    $outlet = outletAtRegion($merchant, $outletRegion);
    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/service-area", [
        'type' => 'province',
        'province_id' => $otherRegion['province']->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_service_area');
});

it('derives open availability within operating hours', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-05 12:00:00', 'Asia/Jakarta'));
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create([
        'merchant_id' => $merchant->id,
        'operating_hours' => [
            'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '22:00'],
        ],
    ]);
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/availability")
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.reason', null)
        ->assertJsonPath('data.merchant_status', 'active')
        ->assertJsonPath('data.outlet_status', 'active')
        ->assertJsonPath('data.schedule.open', '08:00')
        ->assertJsonPath('data.schedule.close', '22:00');
});

it('closes availability when the merchant or outlet is not active', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-05 12:00:00', 'Asia/Jakarta'));
    $schedule = ['monday' => ['is_open' => true, 'open' => '08:00', 'close' => '22:00']];

    ['owner' => $inactiveOwner, 'merchant' => $inactiveMerchant] = operationsOwner(['status' => 'inactive']);
    $inactiveOutlet = MerchantOutlet::factory()->create([
        'merchant_id' => $inactiveMerchant->id,
        'operating_hours' => $schedule,
    ]);
    Sanctum::actingAs($inactiveOwner);
    $this->getJson("/api/v1/merchant/operations/outlets/{$inactiveOutlet->id}/availability")
        ->assertOk()->assertJsonPath('data.reason', 'merchant_inactive');

    ['owner' => $suspendedOwner, 'merchant' => $suspendedMerchant] = operationsOwner(['status' => 'suspended']);
    $suspendedOutlet = MerchantOutlet::factory()->create([
        'merchant_id' => $suspendedMerchant->id,
        'operating_hours' => $schedule,
    ]);
    Sanctum::actingAs($suspendedOwner);
    $this->getJson("/api/v1/merchant/operations/outlets/{$suspendedOutlet->id}/availability")
        ->assertOk()->assertJsonPath('data.reason', 'merchant_suspended');

    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $inactiveOutlet = MerchantOutlet::factory()->inactive()->create([
        'merchant_id' => $merchant->id,
        'operating_hours' => $schedule,
    ]);
    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/merchant/operations/outlets/{$inactiveOutlet->id}/availability")
        ->assertOk()->assertJsonPath('data.reason', 'outlet_inactive');
});

it('closes availability outside operating hours and on scheduled closed days', function () {
    $schedule = ['monday' => ['is_open' => true, 'open' => '08:00', 'close' => '22:00']];

    $this->travelTo(CarbonImmutable::parse('2026-01-05 23:00:00', 'Asia/Jakarta'));
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create([
        'merchant_id' => $merchant->id,
        'operating_hours' => $schedule,
    ]);
    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/availability")
        ->assertOk()->assertJsonPath('data.reason', 'outside_operating_hours');

    $this->travelTo(CarbonImmutable::parse('2026-01-05 12:00:00', 'Asia/Jakarta'));
    $closedOutlet = MerchantOutlet::factory()->create([
        'merchant_id' => $merchant->id,
        'operating_hours' => ['monday' => ['is_open' => false]],
    ]);
    $this->getJson("/api/v1/merchant/operations/outlets/{$closedOutlet->id}/availability")
        ->assertOk()->assertJsonPath('data.reason', 'scheduled_closed');
});

it('updates the logo through the storage contract', function () {
    $this->app->bind(ObjectStorage::class, fn () => fakeOperationsObjectStorage());
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    Sanctum::actingAs($owner);

    $key = "merchants/{$merchant->id}/logo/asset.png";

    $this->patchJson('/api/v1/merchant/operations/profile', ['logo' => $key])
        ->assertOk()
        ->assertJsonPath('data.logo', $key)
        ->assertJsonPath('data.logo_url', 'https://storage.test/'.$key);

    expect($merchant->refresh()->logo)->toBe($key);
});

it('rejects a logo that does not belong to the merchant', function () {
    $this->app->bind(ObjectStorage::class, fn () => fakeOperationsObjectStorage());
    ['owner' => $owner] = operationsOwner();
    Sanctum::actingAs($owner);

    $this->patchJson('/api/v1/merchant/operations/profile', [
        'logo' => 'merchants/00000000-0000-0000-0000-000000000000/logo/asset.png',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'upload_invalid');
});

it('issues an operational upload for a merchant logo', function () {
    $this->app->bind(ObjectStorage::class, fn () => fakeOperationsObjectStorage());
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/v1/merchant/operations/uploads', [
        'purpose' => 'logo',
        'file_name' => 'logo.png',
        'mime_type' => 'image/png',
        'file_size' => 1024,
    ])
        ->assertCreated()
        ->assertJsonPath('data.object_key', fn (string $key): bool => str_starts_with($key, "merchants/{$merchant->id}/logo/"));

    expect($response->json('data.upload_url'))->toBe('https://upload.test/'.$response->json('data.object_key'));
    expect($merchant->refresh()->logo)->toBeNull();
});

it('issues an outlet upload for a manager', function () {
    $this->app->bind(ObjectStorage::class, fn () => fakeOperationsObjectStorage());
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager');
    Sanctum::actingAs($manager);

    $this->postJson('/api/v1/merchant/operations/uploads', [
        'purpose' => 'outlet',
        'file_name' => 'front.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 2048,
    ])
        ->assertCreated()
        ->assertJsonPath('data.object_key', fn (string $key): bool => str_starts_with($key, "merchants/{$merchant->id}/outlets/"));
});

it('denies an operational upload for staff and for an invalid mime type', function () {
    $this->app->bind(ObjectStorage::class, fn () => fakeOperationsObjectStorage());
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $staff] = operationsEmployee($merchant, 'outlet_staff');
    Sanctum::actingAs($staff);

    $this->postJson('/api/v1/merchant/operations/uploads', [
        'purpose' => 'outlet',
        'file_name' => 'front.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 2048,
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');

    ['owner' => $owner] = operationsOwner();
    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/merchant/operations/uploads', [
        'purpose' => 'logo',
        'file_name' => 'logo.exe',
        'mime_type' => 'application/x-msdownload',
        'file_size' => 2048,
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('creates an outlet employee account with an outlet assignment', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/employees", [
        'email' => 'staff@example.com',
        'phone' => '08123456789',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'outlet_staff',
    ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'outlet_staff')
        ->assertJsonPath('data.user.email', 'staff@example.com')
        ->assertJsonPath('data.user.phone', '08123456789');

    $employee = User::query()->where('email', 'staff@example.com')->sole();

    $assignment = MerchantOutletUser::query()
        ->where('outlet_id', $outlet->id)
        ->where('user_id', $employee->id)
        ->sole();

    expect($employee->hasVerifiedEmail())->toBeTrue()
        ->and($assignment->role->value)->toBe('outlet_staff')
        ->and($employee->hasRole('outlet_staff'))->toBeFalse();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'staff@example.com',
        'password' => 'password123',
    ])->assertOk();
});

it('rejects creating an employee with an existing email or mismatched password', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/employees", [
        'email' => $owner->email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'outlet_manager',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/employees", [
        'email' => 'fresh@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different123',
        'role' => 'outlet_manager',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/employees", [
        'email' => 'fresh@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'owner',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');

    expect(User::query()->where('email', 'fresh@example.com')->exists())->toBeFalse();
});

it('denies creating an outlet employee for staff', function () {
    ['merchant' => $merchant] = operationsOwner();
    ['user' => $staff, 'outlet' => $outlet] = operationsEmployee($merchant, 'outlet_staff');
    Sanctum::actingAs($staff);

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/employees", [
        'email' => 'staff2@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'outlet_staff',
    ])->assertStatus(403);
});

it('authorizes outlet access through assignments alone, without any global Spatie role', function () {
    ['merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $manager = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($manager->id)->manager()->create();

    expect($manager->hasRole('outlet_manager'))->toBeFalse();

    Sanctum::actingAs($manager);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours", [
        'monday' => ['is_open' => true, 'open' => '09:00', 'close' => '21:00'],
    ])
        ->assertOk()
        ->assertJsonPath('data.monday.open', '09:00');
});

it('scopes capabilities to the assigned outlet for a multi-outlet employee', function () {
    ['merchant' => $merchant] = operationsOwner();
    $outletA = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $outletB = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $employee = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outletA)->forUser($employee->id)->manager()->create();
    MerchantOutletUser::factory()->forOutlet($outletB)->forUser($employee->id)->staff()->create();

    Sanctum::actingAs($employee);

    $this->putJson("/api/v1/merchant/operations/outlets/{$outletA->id}/operating-hours", [
        'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '20:00'],
    ])->assertOk();

    $this->putJson("/api/v1/merchant/operations/outlets/{$outletB->id}/operating-hours", [
        'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '20:00'],
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_capability_forbidden');

    $this->getJson("/api/v1/merchant/operations/outlets/{$outletB->id}/operating-hours")
        ->assertOk();
});

it('denies outlet management capabilities to a staff-only assignment', function () {
    ['merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $staff = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($staff->id)->staff()->create();
    Sanctum::actingAs($staff);

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}")->assertOk();

    $this->patchJson("/api/v1/merchant/operations/outlets/{$outlet->id}", ['name' => 'Nope'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_capability_forbidden');

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users")
        ->assertStatus(403)
        ->assertJsonPath('code', 'outlet_capability_forbidden');
});

it('creates an outlet manager employee with only an outlet assignment', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/employees", [
        'email' => 'manager@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'outlet_manager',
    ])
        ->assertCreated()
        ->assertJsonPath('data.role', 'outlet_manager');

    $employee = User::query()->where('email', 'manager@example.com')->sole();
    $assignment = MerchantOutletUser::query()->where('user_id', $employee->id)->sole();

    expect($assignment->role->value)->toBe('outlet_manager')
        ->and($employee->hasRole('outlet_manager'))->toBeFalse();
});

it('grants a manager the full outlet capability set on the assigned outlet', function () {
    ['merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    ['user' => $manager] = operationsEmployee($merchant, 'outlet_manager', $outlet);
    Sanctum::actingAs($manager);

    $target = $this->plainUser();

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}")->assertOk();
    $this->patchJson("/api/v1/merchant/operations/outlets/{$outlet->id}", ['name' => 'Manager Edit'])->assertOk();
    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users")->assertOk();
    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users", [
        'user_id' => $target->id,
        'role' => 'outlet_staff',
    ])->assertCreated();
    $this->patchJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users/{$target->id}", [
        'role' => 'outlet_staff',
    ])->assertOk();
    $this->deleteJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users/{$target->id}")->assertNoContent();
    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours", [
        'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '20:00'],
    ])->assertOk();
    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/service-area", [
        'type' => 'radius',
        'radius_km' => 3,
    ])->assertOk();
    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/deactivate")->assertOk();
    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/activate")->assertOk();
    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/availability")->assertOk();
});

it('limits a staff assignment to read-only outlet capabilities', function () {
    ['merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    ['user' => $staff] = operationsEmployee($merchant, 'outlet_staff', $outlet);
    Sanctum::actingAs($staff);

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}")->assertOk();
    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours")->assertOk();
    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/service-area")->assertOk();
    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/availability")->assertOk();

    $this->patchJson("/api/v1/merchant/operations/outlets/{$outlet->id}", ['name' => 'Nope'])->assertStatus(403);
    $this->postJson("/api/v1/merchant/operations/outlets/{$outlet->id}/deactivate")->assertStatus(403);
    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/operating-hours", [
        'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '20:00'],
    ])->assertStatus(403);
    $this->putJson("/api/v1/merchant/operations/outlets/{$outlet->id}/service-area", [
        'type' => 'radius',
        'radius_km' => 3,
    ])->assertStatus(403);
    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}/users")->assertStatus(403);
});

it('changes one outlet assignment without touching another', function () {
    ['owner' => $owner, 'merchant' => $merchant] = operationsOwner();
    $outletA = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $outletB = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $employee = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outletA)->forUser($employee->id)->manager()->create();
    MerchantOutletUser::factory()->forOutlet($outletB)->forUser($employee->id)->staff()->create();

    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/merchant/operations/outlets/{$outletA->id}/users/{$employee->id}", [
        'role' => 'outlet_staff',
    ])
        ->assertOk()
        ->assertJsonPath('data.role', 'outlet_staff');

    $assignments = MerchantOutletUser::query()
        ->where('user_id', $employee->id)
        ->get()
        ->keyBy('outlet_id');

    expect($assignments[$outletA->id]->role->value)->toBe('outlet_staff')
        ->and($assignments[$outletB->id]->role->value)->toBe('outlet_staff')
        ->and($employee->fresh()->roles)->toHaveCount(0);
});

it('does not grant merchant operations access from a global role without a merchant context', function () {
    ['merchant' => $merchant] = operationsOwner();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $stranger = $this->merchantUser();
    expect($stranger->hasRole('merchant'))->toBeTrue();

    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/merchant/operations/outlets/{$outlet->id}")->assertStatus(403);
    $this->getJson('/api/v1/merchant/operations')->assertStatus(403);
});
