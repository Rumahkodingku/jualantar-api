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

it('lets the owner perform any capability on an owned outlet', function () {
    $owner = $this->plainUser();
    $merchant = Merchant::factory()->forUser($owner->id)->create();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    Sanctum::actingAs($owner);

    $authorization = app(MerchantOperationsAuthorization::class);

    expect($authorization->authorizeOutletAction($outlet->id, 'merchant.operations.hours.update')->isOk())->toBeTrue()
        ->and($authorization->authorizeOutletAction($outlet->id, 'merchant.operations.outlet_users.assign')->isOk())->toBeTrue();
});

it('authorizes a capability through the role on the assigned outlet', function () {
    $owner = $this->plainUser();
    $merchant = Merchant::factory()->forUser($owner->id)->create();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $manager = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($manager->id)->manager()->create();
    Sanctum::actingAs($manager);

    $authorization = app(MerchantOperationsAuthorization::class);

    expect($authorization->authorizeOutletAction($outlet->id, 'merchant.operations.hours.update')->isOk())->toBeTrue()
        ->and($authorization->authorizeOutletAction($outlet->id, 'merchant.operations.outlet_users.assign')->isOk())->toBeTrue();
});

it('denies a write capability for staff while allowing read', function () {
    $owner = $this->plainUser();
    $merchant = Merchant::factory()->forUser($owner->id)->create();
    $outlet = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $staff = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outlet)->forUser($staff->id)->staff()->create();
    Sanctum::actingAs($staff);

    $authorization = app(MerchantOperationsAuthorization::class);

    expect($authorization->authorizeOutletAction($outlet->id, 'merchant.operations.hours.view')->isOk())->toBeTrue();

    $denied = $authorization->authorizeOutletAction($outlet->id, 'merchant.operations.hours.update');

    expect($denied->isErr())->toBeTrue()
        ->and($denied->error()->code)->toBe('outlet_capability_forbidden')
        ->and($denied->error()->status)->toBe(403);
});

it('does not leak a capability from one outlet assignment to another', function () {
    $owner = $this->plainUser();
    $merchant = Merchant::factory()->forUser($owner->id)->create();
    $outletA = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $outletB = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $employee = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($outletA)->forUser($employee->id)->manager()->create();
    MerchantOutletUser::factory()->forOutlet($outletB)->forUser($employee->id)->staff()->create();
    Sanctum::actingAs($employee);

    $authorization = app(MerchantOperationsAuthorization::class);

    expect($authorization->authorizeOutletAction($outletA->id, 'merchant.operations.hours.update')->isOk())->toBeTrue();

    $denied = $authorization->authorizeOutletAction($outletB->id, 'merchant.operations.hours.update');

    expect($denied->isErr())->toBeTrue()
        ->and($denied->error()->code)->toBe('outlet_capability_forbidden');
});

it('keeps the 403 and 404 distinction for contextual authorization', function () {
    $owner = $this->plainUser();
    $merchant = Merchant::factory()->forUser($owner->id)->create();
    $assigned = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);
    $sibling = MerchantOutlet::factory()->create(['merchant_id' => $merchant->id]);

    $foreignMerchant = Merchant::factory()->create();
    $foreignOutlet = MerchantOutlet::factory()->create(['merchant_id' => $foreignMerchant->id]);

    $employee = $this->plainUser();
    MerchantOutletUser::factory()->forOutlet($assigned)->forUser($employee->id)->manager()->create();
    Sanctum::actingAs($employee);

    $authorization = app(MerchantOperationsAuthorization::class);

    $siblingResult = $authorization->authorizeOutletAction($sibling->id, 'merchant.operations.hours.view');
    $foreignResult = $authorization->authorizeOutletAction($foreignOutlet->id, 'merchant.operations.hours.view');

    expect($siblingResult->isErr())->toBeTrue()
        ->and($siblingResult->error()->code)->toBe('outlet_scope_forbidden')
        ->and($foreignResult->isErr())->toBeTrue()
        ->and($foreignResult->error()->code)->toBe('outlet_not_found');
});
