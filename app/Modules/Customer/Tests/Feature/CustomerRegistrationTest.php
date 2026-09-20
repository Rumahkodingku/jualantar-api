<?php

use App\Modules\Communications\Contracts\Communications;
use App\Modules\Customer\Application\Actions\RegisterCustomer;
use App\Modules\Customer\Domain\Models\Customer;
use Tests\Support\FakeCommunications;

beforeEach(function () {
    $this->seedRbac();

    $this->communications = new FakeCommunications;
    app()->instance(Communications::class, $this->communications);
});

function customerRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'customer@example.com',
        'phone' => '081234567890',
        'username' => 'thomas',
        'full_name' => 'Thomas Alberto',
        'password' => 'StrongPassword123',
        'password_confirmation' => 'StrongPassword123',
    ], $overrides);
}

it('registers a customer, creates the profile, assigns the role and sends verification', function () {
    $this->postJson('/api/v1/customers/register', customerRegistrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.email', 'customer@example.com')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonStructure(['data' => ['user_id', 'email', 'email_verified']]);

    $user = $this->userByEmail('customer@example.com');

    expect($user)->not->toBeNull()
        ->and($user->hasRole('customer'))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone)->toBe('+6281234567890');

    expect(Customer::where('user_id', $user->id)->where('username', 'thomas')->exists())->toBeTrue();

    expect($this->communications->sent)->toHaveCount(1)
        ->and($this->communications->last()->recipientAddress)->toBe('customer@example.com')
        ->and($this->communications->last()->type)->toBe('identity.email_verification');
});

it('rejects a duplicate email', function () {
    $this->plainUser(['email' => 'customer@example.com']);

    $this->postJson('/api/v1/customers/register', customerRegistrationPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.email.0', fn ($message) => is_string($message));

    expect(Customer::where('username', 'thomas')->exists())->toBeFalse();
});

it('rejects a duplicate phone after normalization', function () {
    $this->plainUser(['phone' => '+6281234567890']);

    $this->postJson('/api/v1/customers/register', customerRegistrationPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('rejects a duplicate username', function () {
    Customer::factory()->create(['username' => 'thomas']);

    $this->postJson('/api/v1/customers/register', customerRegistrationPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('rejects a weak password', function () {
    $this->postJson('/api/v1/customers/register', customerRegistrationPayload([
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['password']]);
});

it('ignores a client supplied role and keeps the customer role', function () {
    $this->postJson('/api/v1/customers/register', customerRegistrationPayload([
        'role' => 'super-admin',
        'status' => 'active',
        'email_verified_at' => now()->toIso8601String(),
    ]))->assertCreated();

    $user = $this->userByEmail('customer@example.com');

    expect($user->getRoleNames()->all())->toBe(['customer'])
        ->and($user->email_verified_at)->toBeNull();
});

it('rolls back the whole registration when the profile cannot be created', function () {
    Customer::factory()->create(['username' => 'thomas']);

    $result = app(RegisterCustomer::class)(customerRegistrationPayload());

    expect($result->isErr())->toBeTrue()
        ->and($this->userByEmail('customer@example.com'))->toBeNull()
        ->and(Customer::where('username', 'thomas')->count())->toBe(1);
});

it('rate limits customer registration', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/customers/register', customerRegistrationPayload([
            'email' => "customer{$i}@example.com",
            'phone' => "0812345678{$i}",
            'username' => "user{$i}",
        ]))->assertCreated();
    }

    $this->postJson('/api/v1/customers/register', customerRegistrationPayload([
        'email' => 'customer99@example.com',
        'phone' => '081234567899',
        'username' => 'user99',
    ]))
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited');
});
