<?php

use App\Modules\Communications\Contracts\Communications;
use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Infrastructure\Verification\VerificationUrlBuilder;
use Tests\Support\FakeCommunications;

beforeEach(function () {
    $this->seedRbac();

    $this->communications = new FakeCommunications;
    app()->instance(Communications::class, $this->communications);
});

it('builds a merchant verification link that points at the frontend', function () {
    config(['merchant.app_url' => 'https://merchant.test']);
    $user = $this->merchantUser();

    $url = app(VerificationUrlBuilder::class)->forUser($user);

    expect($url)
        ->toContain('https://merchant.test/merchant/verify-email')
        ->toContain('id='.$user->id)
        ->toContain('signature=');
});

it('falls back to the API verification url when no frontend url is configured', function () {
    config(['merchant.app_url' => null]);
    $user = $this->merchantUser();

    expect(app(VerificationUrlBuilder::class)->forUser($user))
        ->toContain('/api/v1/auth/email/verify/');
});

it('sends a verification communication to a merchant user', function () {
    config(['merchant.app_url' => 'https://merchant.test']);
    $user = $this->merchantUser();

    app(EmailVerification::class)->send($user->id);

    $sent = $this->communications->last();

    expect($sent)->not->toBeNull()
        ->and($sent->type)->toBe('identity.email_verification')
        ->and($sent->template)->toBe('email.identity.email-verification')
        ->and($sent->recipientAddress)->toBe($user->email)
        ->and($sent->payload['verification_url'])->toContain('https://merchant.test/merchant/verify-email');
});

it('sends a verification communication to a non-merchant user', function () {
    $user = $this->customerUser();

    app(EmailVerification::class)->send($user->id);

    $sent = $this->communications->last();

    expect($sent)->not->toBeNull()
        ->and($sent->recipientAddress)->toBe($user->email)
        ->and($sent->payload['verification_url'])->toContain('/api/v1/auth/email/verify/');
});
