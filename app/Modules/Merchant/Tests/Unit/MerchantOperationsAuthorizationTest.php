<?php

use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('resolves the owned merchant and all of its outlets', function () {
    $owner = $this->plainUser();
    $merchant = Merchant::factory()->forUser($owner->id)->create();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $authorization = app(MerchantOperationsAuthorization::class);

    expect($authorization->ownedMerchant()->unwrap()->id)->toBe($merchant->id)
        ->and($authorization->authorizedOutlet($outlet->id)->isOk())->toBeTrue()
        ->and($authorization->outletQuery()->count())->toBe(1);
});

it('returns not found when the user owns no merchant', function () {
    Sanctum::actingAs($this->plainUser());

    $result = app(MerchantOperationsAuthorization::class)->ownedMerchant();

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('merchant_not_found');
});

it('scopes employees to assigned outlets and distinguishes same-merchant outlets', function () {
    $owner = $this->plainUser();
    $merchant = Merchant::factory()->forUser($owner->id)->create();
    $assigned = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $sibling = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $employee = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($assigned)->forUser($employee->id)->create();
    Sanctum::actingAs($employee);

    $authorization = app(MerchantOperationsAuthorization::class);

    expect($authorization->merchantContext()->unwrap()->id)->toBe($merchant->id)
        ->and($authorization->authorizedOutlet($assigned->id)->isOk())->toBeTrue()
        ->and($authorization->outletQuery()->count())->toBe(1);

    $siblingResult = $authorization->authorizedOutlet($sibling->id);

    expect($siblingResult->isErr())->toBeTrue()
        ->and($siblingResult->error()->code)->toBe('outlet_scope_forbidden');
});

it('hides foreign outlets behind not found', function () {
    $owner = $this->plainUser();
    Merchant::factory()->forUser($owner->id)->create();

    $foreignMerchant = Merchant::factory()->create();
    $foreignOutlet = MerchantOutlet::factory()->create(['merchant_id' => $foreignMerchant->id]);

    Sanctum::actingAs($this->plainUser());

    $result = app(MerchantOperationsAuthorization::class)->authorizedOutlet($foreignOutlet->id);

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('outlet_not_found');
});
