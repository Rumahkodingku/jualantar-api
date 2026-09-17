<?php

use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\MerchantType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
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
    $this->seedRbac();
});

function fakeApprovalObjectStorage(): ObjectStorage
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

/**
 * Drive the full registration flow so the application has a real snapshot and
 * approval.
 *
 * @return array<string, mixed>
 */
function submittedMerchantApplication(): array
{
    $owner = test()->plainUser();
    Sanctum::actingAs($owner);

    $merchant = Merchant::factory()->blankDraft($owner->id)->create();
    $service = test()->newService(['name' => 'JAfood']);
    $category = test()->newServiceCategory(['service_id' => $service->id]);
    $village = test()->newVillage();
    $bank = test()->newBank();

    $merchant->update([
        'business_name' => 'Warung Approval',
        'slug' => 'warung-approval',
        'type' => MerchantType::Individual,
        'service_id' => $service->id,
        'logo' => "merchants/{$merchant->id}/logo/asset",
    ]);

    MerchantIdentity::factory()->create(['merchant_id' => $merchant->id]);
    MerchantCategory::factory()->create(['merchant_id' => $merchant->id, 'category_id' => $category->id]);
    MerchantOutlet::factory()->create([
        'merchant_id' => $merchant->id,
        'village_id' => $village->id,
        'status' => OutletStatus::Active,
        'photos' => ["merchants/{$merchant->id}/outlets/front.jpg"],
    ]);
    MerchantDocument::factory()->create(['merchant_id' => $merchant->id]);
    $payoutAccount = test()->newPayoutAccountForMerchant($merchant->id, ['bank_id' => $bank->id]);

    test()->postJson('/api/v1/merchants/registration/submit')->assertOk();

    $application = $merchant->applications()->firstOrFail();
    $approval = $application->approval()->firstOrFail();

    return compact('owner', 'merchant', 'application', 'approval', 'service', 'category', 'village', 'bank', 'payoutAccount');
}

function verifyAllApprovalSubjects(MerchantApproval $approval): void
{
    $snapshot = $approval->application->snapshots()->orderByDesc('version')->firstOrFail();

    foreach ($snapshot->snapshot['subjects'] as $type => $entry) {
        if ($entry === null) {
            continue;
        }

        $list = array_is_list($entry) ? $entry : [$entry];

        foreach ($list as $subject) {
            test()->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/reviews", [
                'component' => MerchantApprovalSubjectType::from($type)->component()->value,
                'subject_type' => $type,
                'subject_id' => $subject['subject_id'],
                'status' => 'verified',
            ])->assertCreated();
        }
    }
}

function approvalUser(string ...$permissions)
{
    $user = test()->plainUser();

    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

it('rejects a guest from the approval queue', function () {
    $this->getJson('/api/v1/admin/merchant-approvals')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('forbids a user without approval permissions', function () {
    $this->actingAsCustomer();

    $this->getJson('/api/v1/admin/merchant-approvals')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('returns the approval queue summary', function () {
    ['approval' => $approval] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();

    $this->getJson('/api/v1/admin/merchant-approvals/summary')
        ->assertOk()
        ->assertJsonPath('data.by_status.pending', 1)
        ->assertJsonPath('data.unassigned', 1)
        ->assertJsonPath('data.assigned_to_me', 0);
});

it('lists and filters the approval queue', function () {
    submittedMerchantApplication();
    $this->actingAsSuperAdmin();

    $this->getJson('/api/v1/admin/merchant-approvals')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.application.status', 'pending')
        ->assertJsonPath('data.0.merchant.business_name', 'Warung Approval')
        ->assertJsonPath('data.0.merchant.type', MerchantType::Individual->value)
        ->assertJsonPath('data.0.merchant.service.name', 'JAfood');

    $this->getJson('/api/v1/admin/merchant-approvals?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/admin/merchant-approvals?status=approved')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/v1/admin/merchant-approvals?assignment=unassigned')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/admin/merchant-approvals?search=warung')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/admin/merchant-approvals?search=nonexistent')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows the approval detail with snapshot, reviews, revisions and events', function () {
    ['approval' => $approval, 'merchant' => $merchant, 'category' => $category, 'village' => $village] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->app->bind(ObjectStorage::class, fn () => fakeApprovalObjectStorage());

    $document = $merchant->documents()->firstOrFail();

    $this->getJson("/api/v1/admin/merchant-approvals/{$approval->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $approval->id)
        ->assertJsonPath('data.application.status', 'pending')
        ->assertJsonPath('data.current_snapshot.version', 1)
        ->assertJsonPath('data.current_snapshot.data.subjects.service.data.name', 'JAfood')
        ->assertJsonPath('data.current_snapshot.data.subjects.merchant_category.0.data.name', $category->name)
        ->assertJsonPath('data.current_snapshot.data.subjects.merchant_outlet.0.data.geography.village', $village->name)
        ->assertJsonPath('data.current_snapshot.data.subjects.merchant_outlet.0.data.photos_url.0', 'https://storage.test/merchants/'.$merchant->id.'/outlets/front.jpg')
        ->assertJsonPath('data.current_snapshot.data.subjects.merchant.data.logo_url', 'https://storage.test/'.$merchant->logo)
        ->assertJsonPath('data.current_snapshot.data.subjects.merchant_document.0.data.url', 'https://storage.test/'.$document->object_key)
        ->assertJsonCount(1, 'data.events');
});

it('claims a pending approval and moves the application to in_review', function () {
    ['approval' => $approval, 'application' => $application] = submittedMerchantApplication();
    $admin = $this->actingAsSuperAdmin();

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.assigned_to', $admin->id);

    expect($application->refresh()->status)->toBe(MerchantApplicationStatus::InReview);

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_state_transition');
});

it('releases an approval and returns the application to pending', function () {
    ['approval' => $approval, 'application' => $application] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/release")
        ->assertOk();

    expect($application->refresh()->status)->toBe(MerchantApplicationStatus::Pending)
        ->and($approval->refresh()->assigned_to)->toBeNull();
});

it('refuses release by a reviewer that is not assigned', function () {
    ['approval' => $approval] = submittedMerchantApplication();

    $assignee = approvalUser('merchant.approval.view', 'merchant.approval.claim');
    $other = approvalUser('merchant.approval.view', 'merchant.approval.claim');

    Sanctum::actingAs($assignee);
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    Sanctum::actingAs($other);
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/release")
        ->assertStatus(403)
        ->assertJsonPath('code', 'not_assigned');
});

function approvalSubject(MerchantApproval $approval, string $type, int $index = 0): array
{
    $snapshot = $approval->application->snapshots()->orderByDesc('version')->firstOrFail();
    $entry = $snapshot->snapshot['subjects'][$type];
    $list = array_is_list($entry) ? $entry : [$entry];

    return $list[$index];
}

it('creates and updates a component review without duplicating the current state', function () {
    ['approval' => $approval] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    $identity = approvalSubject($approval, 'merchant_identity');

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/reviews", [
        'component' => 'identity',
        'subject_type' => 'merchant_identity',
        'subject_id' => $identity['subject_id'],
        'status' => 'verified',
        'note' => 'Looks good.',
    ])->assertCreated();

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/reviews", [
        'component' => 'identity',
        'subject_type' => 'merchant_identity',
        'subject_id' => $identity['subject_id'],
        'status' => 'rejected',
        'note' => 'Blurry.',
    ])->assertOk();

    expect($approval->reviews()->count())->toBe(1)
        ->and($approval->reviews()->first()->status->value)->toBe('rejected');
});

it('rejects a review for a subject outside the current submission', function () {
    ['approval' => $approval] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/reviews", [
        'component' => 'identity',
        'subject_type' => 'merchant_identity',
        'subject_id' => fake()->uuid(),
        'status' => 'verified',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'business_rule_violation');
});

it('blocks approval until all required subjects are verified', function () {
    ['approval' => $approval] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/approve")
        ->assertStatus(422)
        ->assertJsonPath('code', 'business_rule_violation');
});

it('approves the application and activates the merchant', function () {
    ['approval' => $approval, 'application' => $application, 'merchant' => $merchant] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    verifyAllApprovalSubjects($approval);

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.decision', 'approved');

    expect($application->refresh()->status)->toBe(MerchantApplicationStatus::Approved)
        ->and($merchant->refresh()->status)->toBe(MerchantStatus::Active);
});

it('rejects the application and leaves the merchant inactive', function () {
    ['approval' => $approval, 'application' => $application, 'merchant' => $merchant] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/reject", [
        'reason' => 'Requirements not met.',
    ])->assertOk();

    expect($application->refresh()->status)->toBe(MerchantApplicationStatus::Rejected)
        ->and($merchant->refresh()->status)->toBe(MerchantStatus::Inactive)
        ->and($approval->refresh()->decision->value)->toBe('rejected');
});

it('requests a revision and resets only the changed or rejected reviews', function () {
    ['approval' => $approval, 'application' => $application, 'owner' => $owner] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    $identity = approvalSubject($approval, 'merchant_identity');
    $service = approvalSubject($approval, 'service');

    foreach ([['identity', 'merchant_identity', $identity], ['service', 'service', $service]] as [$component, $type, $subject]) {
        $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/reviews", [
            'component' => $component,
            'subject_type' => $type,
            'subject_id' => $subject['subject_id'],
            'status' => 'verified',
        ])->assertCreated();
    }

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/revision", [
        'note' => 'Fix identity.',
        'items' => [[
            'component' => 'identity',
            'subject_type' => 'merchant_identity',
            'subject_id' => $identity['subject_id'],
            'reason' => 'KTP is unclear.',
        ]],
    ])->assertCreated();

    expect($application->refresh()->status)->toBe(MerchantApplicationStatus::RevisionRequired);

    // Merchant edits the identity while in revision_required.
    Sanctum::actingAs($owner);
    $this->putJson('/api/v1/merchants/registration/identity', [
        'id_type' => 'ktp',
        'id_number' => '6171xxxxxxxxxxxx',
        'full_name' => 'Budi Santoso Updated',
    ])->assertOk();

    $this->postJson('/api/v1/merchants/registration/submit')->assertOk();

    $identityReview = $approval->reviews()
        ->where('subject_type', 'merchant_identity')->firstOrFail();
    $serviceReview = $approval->reviews()
        ->where('subject_type', 'service')->firstOrFail();

    expect($application->refresh()->status)->toBe(MerchantApplicationStatus::Pending)
        ->and($identityReview->status->value)->toBe('pending')
        ->and($serviceReview->status->value)->toBe('verified');
});

it('creates a new application when a merchant re-applies after rejection', function () {
    ['approval' => $approval, 'owner' => $owner, 'merchant' => $merchant] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/reject", [
        'reason' => 'Rejected.',
    ])->assertOk();

    Sanctum::actingAs($owner);

    $this->postJson('/api/v1/merchants/registration')
        ->assertCreated()
        ->assertJsonPath('data.application.status', 'draft');

    expect($merchant->applications()->count())->toBe(2);
});

it('generates an application number in the MA-YYYYMMDD-NNNNNN format', function () {
    ['application' => $application] = submittedMerchantApplication();

    expect($application->application_number)->toMatch('/^MA-\d{8}-\d{6}$/');
});

it('returns RFC 9457 problem details for approval errors', function () {
    $this->getJson('/api/v1/admin/merchant-approvals/'.fake()->uuid())
        ->assertStatus(401)
        ->assertHeader('content-type', 'application/problem+json');
});

it('keeps snapshots and events immutable', function () {
    ['approval' => $approval] = submittedMerchantApplication();

    $snapshot = $approval->application->snapshots()->firstOrFail();
    $event = $approval->events()->firstOrFail();

    expect($snapshot->update(['version' => 99]))->toBeFalse()
        ->and($event->delete())->toBeFalse()
        ->and($snapshot->refresh()->version)->toBe(1)
        ->and($approval->events()->count())->toBe(1);
});

it('verifies the payout account through the payout contract', function () {
    ['approval' => $approval, 'payoutAccount' => $payoutAccount] = submittedMerchantApplication();
    $this->actingAsSuperAdmin();
    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/claim")->assertOk();

    $payout = approvalSubject($approval, 'payout_account');

    $this->postJson("/api/v1/admin/merchant-approvals/{$approval->id}/reviews", [
        'component' => 'payout',
        'subject_type' => 'payout_account',
        'subject_id' => $payout['subject_id'],
        'status' => 'verified',
    ])->assertCreated();

    expect($payoutAccount->refresh()->status->value)->toBe('active');
});
