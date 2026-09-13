<?php

use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\LegalEntity;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantCategory;
use App\Modules\Merchant\Domain\Models\MerchantDocument;
use App\Modules\Merchant\Domain\Models\MerchantIdentity;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a merchant that references a service and an owner', function () {
    $serviceId = (string) Str::uuid();
    $userId = (string) Str::uuid();

    $merchant = Merchant::factory()->forService($serviceId)->forUser($userId)->create();

    expect($merchant->service_id)->toBe($serviceId)
        ->and($merchant->user_id)->toBe($userId)
        ->and($merchant->status)->toBe(MerchantStatus::Draft);
});

it('rejects a duplicate slug', function () {
    Merchant::factory()->create(['slug' => 'warung-bu-siti']);

    expect(fn () => Merchant::factory()->create(['slug' => 'warung-bu-siti']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects a duplicate merchant and category pair', function () {
    $merchant = Merchant::factory()->create();
    $categoryId = (string) Str::uuid();

    MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $categoryId,
    ]);

    expect(fn () => MerchantCategory::factory()->create([
        'merchant_id' => $merchant->id,
        'category_id' => $categoryId,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('allows a merchant to have multiple outlets with an active or inactive status', function () {
    $merchant = Merchant::factory()->create();

    MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $inactive = MerchantOutlet::factory()->inactive()->create(['merchant_id' => $merchant->id]);

    expect($merchant->outlets()->count())->toBe(2)
        ->and($inactive->status)->toBe(OutletStatus::Inactive);
});

it('keeps documents optional and allows multiple documents', function () {
    $merchant = Merchant::factory()->create();

    expect($merchant->documents()->count())->toBe(0);

    MerchantDocument::factory()->count(3)->create(['merchant_id' => $merchant->id]);

    expect($merchant->documents()->count())->toBe(3);
});

it('keeps the legal entity optional', function () {
    $withoutEntity = Merchant::factory()->create();

    $legalEntity = LegalEntity::factory()->create();
    $withEntity = Merchant::factory()->create(['legal_entity_id' => $legalEntity->id]);

    expect($withoutEntity->legal_entity_id)->toBeNull()
        ->and($withEntity->legalEntity->id)->toBe($legalEntity->id);
});

it('enforces a one-to-one merchant identity', function () {
    $merchant = Merchant::factory()->create();

    MerchantIdentity::factory()->create(['merchant_id' => $merchant->id]);

    expect(fn () => MerchantIdentity::factory()->create(['merchant_id' => $merchant->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('follows the merchant status transition rules', function () {
    expect(MerchantStatus::Draft->canTransitionTo(MerchantStatus::Pending))->toBeTrue()
        ->and(MerchantStatus::Pending->canTransitionTo(MerchantStatus::Active))->toBeTrue()
        ->and(MerchantStatus::Pending->canTransitionTo(MerchantStatus::Rejected))->toBeTrue()
        ->and(MerchantStatus::Rejected->canTransitionTo(MerchantStatus::Draft))->toBeTrue()
        ->and(MerchantStatus::Rejected->canTransitionTo(MerchantStatus::Pending))->toBeTrue()
        ->and(MerchantStatus::Active->canTransitionTo(MerchantStatus::Suspended))->toBeTrue()
        ->and(MerchantStatus::Suspended->canTransitionTo(MerchantStatus::Active))->toBeTrue()
        ->and(MerchantStatus::Draft->canTransitionTo(MerchantStatus::Active))->toBeFalse()
        ->and(MerchantStatus::Active->canTransitionTo(MerchantStatus::Rejected))->toBeFalse();
});

it('stores rejection metadata', function () {
    $merchant = Merchant::factory()->rejected()->create([
        'rejection_stage' => 'outlet',
        'rejection_reason' => 'Alamat outlet tidak valid.',
    ]);

    expect($merchant->status)->toBe(MerchantStatus::Rejected)
        ->and($merchant->rejection_stage->value)->toBe('outlet')
        ->and($merchant->rejection_reason)->toBe('Alamat outlet tidak valid.');
});
