<?php

use App\Modules\BankDirectory\Domain\Models\Bank;
use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Enums\PayoutStatus;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('rejects a guest from listing payout accounts', function () {
    $this->getJson('/api/v1/payout-accounts')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('forbids a user without the payout manage permission', function () {
    Sanctum::actingAs(User::factory()->customer()->create());

    $this->getJson('/api/v1/payout-accounts')
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('lists payout accounts with the resolved bank inside the paginated envelope', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $bank = Bank::factory()->create(['code' => '008', 'name' => 'PT BANK MANDIRI']);
    PayoutAccount::factory()->create(['bank_id' => $bank->id]);
    PayoutAccount::factory()->create();

    $this->getJson('/api/v1/payout-accounts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure([
            'data' => [['id', 'owner_type', 'owner_id', 'bank_id', 'bank', 'account_number', 'account_name', 'is_primary', 'status', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ])
        ->assertJsonPath('data.0.bank.name', 'PT BANK MANDIRI');
});

it('filters payout accounts by owner, status, bank and search', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $ownerId = (string) Str::uuid();
    $bank = Bank::factory()->create();

    PayoutAccount::factory()
        ->forOwner(PayoutOwnerType::Merchant, $ownerId)
        ->active()
        ->create(['bank_id' => $bank->id, 'account_name' => 'Budi Santoso']);

    PayoutAccount::factory()->create();

    $this->getJson('/api/v1/payout-accounts?owner_id='.$ownerId)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.account_name', 'Budi Santoso');

    $this->getJson('/api/v1/payout-accounts?status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', PayoutStatus::Active->value);

    $this->getJson('/api/v1/payout-accounts?bank_id='.$bank->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/payout-accounts?search=budi')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns a validation problem for invalid index query params', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->getJson('/api/v1/payout-accounts?status=bogus')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.status.0', 'The selected status is invalid.');

    $this->getJson('/api/v1/payout-accounts?owner_type=bogus')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('shows a single payout account with its bank', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $bank = Bank::factory()->create(['code' => '002', 'name' => 'PT BANK RAKYAT INDONESIA']);
    $account = PayoutAccount::factory()->create(['bank_id' => $bank->id]);

    $this->getJson("/api/v1/payout-accounts/{$account->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $account->id)
        ->assertJsonPath('data.bank.id', $bank->id)
        ->assertJsonPath('data.bank.code', '002');
});

it('returns a 404 problem when the payout account does not exist', function () {
    Sanctum::actingAs(User::factory()->superAdmin()->create());

    $this->getJson('/api/v1/payout-accounts/'.fake()->uuid())
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});
