<?php

use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Notifications\MerchantVerifyEmailNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seedRbac();
});

it('builds a merchant verification link that points at the frontend', function () {
    config(['merchant.app_url' => 'https://merchant.test']);
    $user = $this->merchantUser();

    $mail = (new MerchantVerifyEmailNotification)->toMail($user);

    expect($mail->actionUrl)
        ->toContain('https://merchant.test/merchant/verify-email')
        ->toContain('id='.$user->id)
        ->toContain('signature=');
});

it('falls back to the API verification url when no frontend url is configured', function () {
    config(['merchant.app_url' => null]);
    $user = $this->merchantUser();

    $mail = (new MerchantVerifyEmailNotification)->toMail($user);

    expect($mail->actionUrl)->toContain('/api/v1/auth/email/verify/');
});

it('sends the merchant notification when resending to a merchant user', function () {
    Notification::fake();
    $user = $this->merchantUser();

    app(EmailVerification::class)->send($user->id);

    Notification::assertSentTo($user, MerchantVerifyEmailNotification::class);
});

it('keeps the default notification for non-merchant users', function () {
    Notification::fake();
    $user = $this->customerUser();

    app(EmailVerification::class)->send($user->id);

    Notification::assertSentTo($user, VerifyEmail::class);
    Notification::assertNotSentTo($user, MerchantVerifyEmailNotification::class);
});
