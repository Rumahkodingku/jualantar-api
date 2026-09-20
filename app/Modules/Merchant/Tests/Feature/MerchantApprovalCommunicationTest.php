<?php

use App\Modules\Communications\Contracts\Communications;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Application\Common\MerchantApprovalCommunicator;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use Tests\Support\FakeCommunications;

beforeEach(function () {
    $this->seedRbac();

    $this->communications = new FakeCommunications;
    app()->instance(Communications::class, $this->communications);
});

/**
 * @return array{0: User, 1: Merchant, 2: MerchantApplication}
 */
function approvalCommunicationContext(): array
{
    $user = test()->plainUser();
    $merchant = Merchant::factory()->forUser($user->id)->create(['business_name' => 'Warung Borneo']);
    $application = MerchantApplication::factory()->forMerchant($merchant->id)->create();

    return [$user, $merchant, $application];
}

it('sends an approval communication to the merchant owner', function () {
    [$user, $merchant, $application] = approvalCommunicationContext();

    app(MerchantApprovalCommunicator::class)->approved($merchant, $application);

    $sent = $this->communications->last();

    expect($sent)->not->toBeNull()
        ->and($sent->type)->toBe('merchant.application.approved')
        ->and($sent->template)->toBe('email.merchant.application-approved')
        ->and($sent->recipientAddress)->toBe($user->email)
        ->and($sent->idempotencyKey)->toBe('merchant.application.approved:'.$application->id)
        ->and($sent->payload['application_number'])->toBe($application->application_number)
        ->and($sent->payload['business_name'])->toBe('Warung Borneo');
});

it('sends a rejection communication with the reason', function () {
    [, $merchant, $application] = approvalCommunicationContext();

    app(MerchantApprovalCommunicator::class)->rejected($merchant, $application, 'Dokumen tidak lengkap');

    $sent = $this->communications->last();

    expect($sent)->not->toBeNull()
        ->and($sent->type)->toBe('merchant.application.rejected')
        ->and($sent->template)->toBe('email.merchant.application-rejected')
        ->and($sent->payload['reason'])->toBe('Dokumen tidak lengkap');
});

it('sends a revision communication with the note', function () {
    [, $merchant, $application] = approvalCommunicationContext();

    app(MerchantApprovalCommunicator::class)->revisionRequested($merchant, $application, 'Perbaiki nama usaha');

    $sent = $this->communications->last();

    expect($sent)->not->toBeNull()
        ->and($sent->type)->toBe('merchant.application.revision_requested')
        ->and($sent->template)->toBe('email.merchant.application-revision-requested')
        ->and($sent->payload['note'])->toBe('Perbaiki nama usaha');
});

it('does not send when the merchant has no owner', function () {
    $merchant = Merchant::factory()->create(['user_id' => null]);
    $application = MerchantApplication::factory()->forMerchant($merchant->id)->create();

    app(MerchantApprovalCommunicator::class)->approved($merchant, $application);

    expect($this->communications->sent)->toBeEmpty();
});
