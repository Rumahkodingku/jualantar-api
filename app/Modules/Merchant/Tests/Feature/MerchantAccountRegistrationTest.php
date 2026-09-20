<?php

use App\Modules\Communications\Contracts\Communications;
use Tests\Support\FakeCommunications;

beforeEach(function () {
    $this->seedRbac();
    config(['merchant.app_url' => 'https://merchant.test']);

    $this->communications = new FakeCommunications;
    app()->instance(Communications::class, $this->communications);
});

/**
 * @return array<string, mixed>
 */
function merchantAccountPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'merchant@example.com',
        'phone' => '081234567890',
        'password' => 'StrongPassword123',
        'password_confirmation' => 'StrongPassword123',
        'terms_accepted' => true,
    ], $overrides);
}

it('registers a merchant account, assigns the role and sends verification', function () {
    $this->postJson('/api/v1/merchants/register', merchantAccountPayload())
        ->assertCreated()
        ->assertJsonPath('data.email', 'merchant@example.com')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonStructure(['data' => ['id', 'email', 'phone', 'email_verified']]);

    $user = $this->userByEmail('merchant@example.com');

    expect($user)->not->toBeNull()
        ->and($user->hasRole('merchant'))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe(['merchant'])
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone)->toBe('+6281234567890');

    $sent = $this->communications->last();

    expect($sent)->not->toBeNull()
        ->and($sent->type)->toBe('identity.email_verification')
        ->and($sent->template)->toBe('email.identity.email-verification')
        ->and($sent->recipientAddress)->toBe('merchant@example.com')
        ->and($sent->payload['verification_url'])
        ->toContain('https://merchant.test/merchant/verify-email')
        ->toContain('signature=');
});

it('rejects a duplicate email', function () {
    $this->plainUser(['email' => 'merchant@example.com']);

    $this->postJson('/api/v1/merchants/register', merchantAccountPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.email.0', fn ($message) => is_string($message));
});

it('rejects a duplicate phone after normalization', function () {
    $this->plainUser(['phone' => '+6281234567890']);

    $this->postJson('/api/v1/merchants/register', merchantAccountPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('requires accepted terms and conditions', function () {
    $this->postJson('/api/v1/merchants/register', merchantAccountPayload([
        'terms_accepted' => false,
    ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['terms_accepted']]);
});

it('rejects a weak password', function () {
    $this->postJson('/api/v1/merchants/register', merchantAccountPayload([
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['password']]);
});

it('ignores a client supplied role and verification timestamp', function () {
    $this->postJson('/api/v1/merchants/register', merchantAccountPayload([
        'role' => 'super-admin',
        'email_verified_at' => now()->toIso8601String(),
    ]))->assertCreated();

    $user = $this->userByEmail('merchant@example.com');

    expect($user->getRoleNames()->all())->toBe(['merchant'])
        ->and($user->email_verified_at)->toBeNull();
});

it('rate limits merchant account registration', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/merchants/register', merchantAccountPayload([
            'email' => "merchant{$i}@example.com",
            'phone' => "0812345678{$i}",
        ]))->assertCreated();
    }

    $this->postJson('/api/v1/merchants/register', merchantAccountPayload([
        'email' => 'merchant99@example.com',
        'phone' => '081234567899',
    ]))
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited');
});
