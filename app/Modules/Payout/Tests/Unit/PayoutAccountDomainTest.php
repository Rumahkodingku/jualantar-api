<?php

use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Enums\PayoutStatus;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('defaults a new payout account to pending and primary', function () {
    $account = PayoutAccount::factory()->create();

    expect($account->status)->toBe(PayoutStatus::Pending)
        ->and($account->is_primary)->toBeTrue();
});

it('follows the payout status transition rules', function () {
    expect(PayoutStatus::Pending->canTransitionTo(PayoutStatus::Active))->toBeTrue()
        ->and(PayoutStatus::Pending->canTransitionTo(PayoutStatus::Rejected))->toBeTrue()
        ->and(PayoutStatus::Active->canTransitionTo(PayoutStatus::Rejected))->toBeFalse()
        ->and(PayoutStatus::Rejected->canTransitionTo(PayoutStatus::Active))->toBeFalse();
});

it('stores a rejection reason', function () {
    $account = PayoutAccount::factory()->rejected()->create([
        'rejection_reason' => 'Nama rekening tidak sesuai.',
    ]);

    expect($account->status)->toBe(PayoutStatus::Rejected)
        ->and($account->rejection_reason)->toBe('Nama rekening tidak sesuai.');
});

it('enforces at most one primary payout account per owner at the database level', function () {
    $ownerId = (string) Str::uuid();

    PayoutAccount::factory()->forOwner(PayoutOwnerType::Merchant, $ownerId)->create();

    expect(fn () => PayoutAccount::factory()->forOwner(PayoutOwnerType::Merchant, $ownerId)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows a secondary account for the same owner', function () {
    $ownerId = (string) Str::uuid();

    PayoutAccount::factory()->forOwner(PayoutOwnerType::Merchant, $ownerId)->create();
    $secondary = PayoutAccount::factory()->forOwner(PayoutOwnerType::Merchant, $ownerId)->secondary()->create();

    expect($secondary->is_primary)->toBeFalse()
        ->and(PayoutAccount::query()->forOwner(PayoutOwnerType::Merchant, $ownerId)->count())->toBe(2);
});

it('allows the same bank to be used by many payout accounts', function () {
    $bankId = 42;

    PayoutAccount::factory()->create(['bank_id' => $bankId]);
    PayoutAccount::factory()->create(['bank_id' => $bankId]);

    expect(PayoutAccount::query()->where('bank_id', $bankId)->count())->toBe(2);
});

it('keeps the merchant status independent from the payout status', function () {
    $merchant = Merchant::factory()->active()->create();

    $account = PayoutAccount::factory()
        ->forOwner(PayoutOwnerType::Merchant, $merchant->id)
        ->create();

    $account->update(['status' => PayoutStatus::Active]);

    expect($merchant->fresh()->status)->toBe(MerchantStatus::Active)
        ->and($account->fresh()->status)->toBe(PayoutStatus::Active);
});
