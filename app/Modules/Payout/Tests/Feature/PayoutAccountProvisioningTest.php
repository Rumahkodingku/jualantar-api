<?php

use App\Modules\Payout\Contracts\PayoutAccountProvisioning;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Enums\PayoutStatus;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use Illuminate\Support\Str;

it('creates a primary pending payout account for an owner', function () {
    $bank = $this->newBank();
    $ownerId = (string) Str::uuid();

    $result = app(PayoutAccountProvisioning::class)->saveForOwner(
        'merchant',
        $ownerId,
        $bank->id,
        '1234567890',
        'Budi Santoso',
    );

    expect($result->isOk())->toBeTrue();

    $data = $result->unwrap();

    expect($data->bankId)->toBe($bank->id)
        ->and($data->bankName)->toBe($bank->name)
        ->and($data->status)->toBe(PayoutStatus::Pending->value);

    expect(PayoutAccount::query()->forOwner(PayoutOwnerType::Merchant, $ownerId)->count())->toBe(1);
});

it('updates the existing primary account without duplicating it', function () {
    $bank = $this->newBank();
    $ownerId = (string) Str::uuid();
    $provisioning = app(PayoutAccountProvisioning::class);

    $provisioning->saveForOwner('merchant', $ownerId, $bank->id, '111', 'Old Name');
    $provisioning->saveForOwner('merchant', $ownerId, $bank->id, '222', 'New Name');

    $accounts = PayoutAccount::query()->forOwner(PayoutOwnerType::Merchant, $ownerId)->get();

    expect($accounts)->toHaveCount(1)
        ->and($accounts->first()->account_number)->toBe('222')
        ->and($accounts->first()->account_name)->toBe('New Name');
});

it('preserves the existing payout status when re-saving', function () {
    $bank = $this->newBank();
    $ownerId = (string) Str::uuid();
    $provisioning = app(PayoutAccountProvisioning::class);

    $provisioning->saveForOwner('merchant', $ownerId, $bank->id, '111', 'Name');

    PayoutAccount::query()->forOwner(PayoutOwnerType::Merchant, $ownerId)->update([
        'status' => PayoutStatus::Active->value,
    ]);

    $result = $provisioning->saveForOwner('merchant', $ownerId, $bank->id, '222', 'Name');

    expect($result->unwrap()->status)->toBe(PayoutStatus::Active->value);
});

it('returns an error result for an invalid bank', function () {
    $result = app(PayoutAccountProvisioning::class)->saveForOwner(
        'merchant',
        (string) Str::uuid(),
        999999,
        '123',
        'Name',
    );

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('invalid_payout_account');
});

it('returns an error result for an unknown owner type', function () {
    $bank = $this->newBank();

    $result = app(PayoutAccountProvisioning::class)->saveForOwner(
        'unknown',
        (string) Str::uuid(),
        $bank->id,
        '123',
        'Name',
    );

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('invalid_payout_account');
});
