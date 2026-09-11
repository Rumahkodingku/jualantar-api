<?php

use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Notification::fake();
});

function emailVerificationUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

it('verifies a user email through the signed link', function () {
    $user = User::factory()->unverified()->create();

    $this->getJson(emailVerificationUrl($user))->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('is idempotent when the email is already verified', function () {
    $user = User::factory()->create();
    $verifiedAt = $user->email_verified_at;

    $this->getJson(emailVerificationUrl($user))->assertNoContent();

    expect($user->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
});

it('rejects a link with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $this->getJson(emailVerificationUrl($user, sha1('other@example.com')))
        ->assertStatus(403)
        ->assertJsonPath('code', 'invalid_verification');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a link without a valid signature', function () {
    $user = User::factory()->unverified()->create();

    $this->getJson("/api/v1/auth/email/verify/{$user->id}/".sha1($user->email))
        ->assertStatus(403);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a verification link for a missing user', function () {
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => (string) Str::uuid(),
        'hash' => sha1('missing@example.com'),
    ]);

    $this->getJson($url)
        ->assertStatus(403)
        ->assertJsonPath('code', 'invalid_verification');
});

it('resends the verification notification', function () {
    $user = User::factory()->unverified()->create();

    $this->postJson('/api/v1/auth/email/verification-notification', ['email' => $user->email])
        ->assertStatus(202);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not resend for an already verified user', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/email/verification-notification', ['email' => $user->email])
        ->assertStatus(202);

    Notification::assertNothingSent();
});

it('rate limits verification resend', function () {
    $user = User::factory()->unverified()->create();

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/auth/email/verification-notification', ['email' => $user->email])
            ->assertStatus(202);
    }

    $this->postJson('/api/v1/auth/email/verification-notification', ['email' => $user->email])
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited');
});
